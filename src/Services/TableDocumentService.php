<?php

namespace Decorate\LaravelTableDocument\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TableDocumentService
{
    /**
     * HTMLファイルの保存先ディレクトリ
     */
    private string $outputDirectory = 'table-documents';

    /**
     * メタデータサービス
     */
    public TableMetadataService $metadataService;

    public function __construct(TableMetadataService $metadataService)
    {
        $this->metadataService = $metadataService;
    }

    /**
     * 全テーブルの情報を取得
     */
    public function getAllTablesInfo(): array
    {
        $tables = $this->getTableNames();
        $tablesInfo = [];

        foreach ($tables as $tableName) {
            $tablesInfo[] = $this->getTableInfo($tableName);
        }

        return $tablesInfo;
    }

    /**
     * HTMLファイルを生成
     */
    public function generateHtmlFile(bool $force = false): array
    {
        $outputPath = $this->getOutputPath();
        //        $metaPath = $this->getMetaPath();

        // 強制生成でない場合、既存ファイルをチェック
        if (! $force && File::exists($outputPath)) {
            $meta = $this->getMetaData();

            return [
                'success' => true,
                'path' => $outputPath,
                'url' => $this->getPublicUrl(),
                'generated_at' => $meta['generated_at'] ?? null,
                'message' => '既存のHTMLファイルが存在します。',
            ];
        }

        // メタデータファイルをリロード
        $this->metadataService->reload();

        // テーブル情報を取得
        $tables = $this->getAllTablesInfo();

        // HTMLを生成
        $html = view('table-document::static', [
            'tables' => $tables,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'database' => env('DB_DATABASE'),
            'has_metadata' => $this->metadataService->exists(),
        ])->render();

        // ディレクトリ作成
        $directory = storage_path("app/public/{$this->outputDirectory}");
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // HTMLファイル保存
        File::put($outputPath, $html);

        // メタデータ保存
        $this->saveMetaData([
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'table_count' => count($tables),
            'database' => env('DB_DATABASE'),
            'file_size' => File::size($outputPath),
            'has_metadata' => $this->metadataService->exists(),
        ]);

        return [
            'success' => true,
            'path' => $outputPath,
            'url' => $this->getPublicUrl(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'message' => 'HTMLファイルを生成しました。',
        ];
    }

    /**
     * メタデータファイルを生成
     */
    public function generateMetadataFile(): bool
    {
        $tables = $this->getAllTablesInfo();
        $this->metadataService->generateSampleMetadata($tables);

        return true;
    }

    /**
     * 生成済みHTMLの情報を取得
     */
    public function getGeneratedHtmlInfo(): ?array
    {
        $outputPath = $this->getOutputPath();

        if (! File::exists($outputPath)) {
            return null;
        }

        $meta = $this->getMetaData();

        return [
            'exists' => true,
            'path' => $outputPath,
            'url' => $this->getPublicUrl(),
            'generated_at' => $meta['generated_at'] ?? File::lastModified($outputPath),
            'file_size' => File::size($outputPath),
            'table_count' => $meta['table_count'] ?? null,
            'database' => $meta['database'] ?? null,
        ];
    }

    /**
     * HTMLファイルのパスを取得
     */
    private function getOutputPath(): string
    {
        return storage_path("app/public/{$this->outputDirectory}/table_definition.html");
    }

    /**
     * メタデータファイルのパスを取得
     */
    private function getMetaPath(): string
    {
        return storage_path("app/public/{$this->outputDirectory}/meta.json");
    }

    /**
     * 公開URLを取得
     */
    private function getPublicUrl(): string
    {
        return asset("storage/{$this->outputDirectory}/table_definition.html");
    }

    /**
     * メタデータを保存
     */
    private function saveMetaData(array $data): void
    {
        File::put($this->getMetaPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * メタデータを取得
     */
    private function getMetaData(): array
    {
        $metaPath = $this->getMetaPath();

        if (! File::exists($metaPath)) {
            return [];
        }

        return json_decode(File::get($metaPath), true) ?? [];
    }

    /**
     * テーブル名一覧を取得
     */
    private function getTableNames(): array
    {
      $tables = array_map(
        fn ($table) => $table->{'Tables_in_'.env('DB_DATABASE')},
        DB::select('SHOW TABLES')
      );

      // 除外するテーブルを削除
      $excludeTables = config('table-document.database.exclude_tables', []);

      return array_filter($tables, function ($tableName) use ($excludeTables) {
        return !in_array($tableName, $excludeTables);
      });
    }

    /**
     * 特定テーブルの詳細情報を取得
     */
    public function getTableInfo(string $tableName): array
    {
        $columns = $this->getColumnsInfo($tableName);
        $indexes = $this->getIndexesInfo($tableName);
        $foreignKeys = $this->getForeignKeysInfo($tableName);
        $tableComment = $this->getTableComment($tableName);

        $tableInfo = [
            'name' => $tableName,
            'comment' => $tableComment,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'primary_key' => $this->getPrimaryKey($tableName),
            'engine' => $this->getTableEngine($tableName),
            'collation' => $this->getTableCollation($tableName),
        ];

        // メタデータで情報を拡張
        return $this->metadataService->enrichTableInfo($tableInfo);
    }

    /**
     * カラム情報を取得
     */
    private function getColumnsInfo(string $tableName): array
    {
        $results = DB::select('
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                COLUMN_COMMENT,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ', [env('DB_DATABASE'), $tableName]);

        $columnsInfo = [];

        foreach ($results as $column) {
            $type = $column->DATA_TYPE;
            if (strpos($column->COLUMN_TYPE, 'enum') === 0) {
                $type = 'enum';
            } elseif (strpos($column->COLUMN_TYPE, 'set') === 0) {
                $type = 'set';
            }

            $columnInfo = [
                'name' => $column->COLUMN_NAME,
                'type' => $type,
                'length' => $column->CHARACTER_MAXIMUM_LENGTH,
                'precision' => $column->NUMERIC_PRECISION,
                'scale' => $column->NUMERIC_SCALE,
                'unsigned' => strpos($column->COLUMN_TYPE, 'unsigned') !== false,
                'nullable' => $column->IS_NULLABLE === 'YES',
                'default' => $column->COLUMN_DEFAULT,
                'comment' => $column->COLUMN_COMMENT,
                'auto_increment' => strpos($column->EXTRA, 'auto_increment') !== false,
                'enum_values' => null,
                'constraints' => [],
            ];

            // ENUM/SET型の値を取得
            if ($type === 'enum' || $type === 'set') {
                $enumValues = $this->getEnumValues($tableName, $column->COLUMN_NAME);
                if (! empty($enumValues)) {
                    $columnInfo['enum_values'] = $enumValues;
                }
            }

            // CHECK制約を取得（最大値・最小値など）
            $constraints = $this->getColumnConstraints($tableName, $column->COLUMN_NAME);
            if (! empty($constraints)) {
                $columnInfo['constraints'] = $constraints;
            }

            $columnsInfo[] = $columnInfo;
        }

        return $columnsInfo;
    }

    /**
     * ENUM型の値を取得
     */
    private function getEnumValues(string $tableName, string $columnName): array
    {
        try {
            $result = DB::select('
                SELECT COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ', [env('DB_DATABASE'), $tableName, $columnName]);

            if (empty($result)) {
                return [];
            }

            $columnType = $result[0]->COLUMN_TYPE;

            // ENUM('value1','value2','value3') から値を抽出
            if (preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
                $values = str_getcsv($matches[1], ',', "'");

                return array_map('trim', $values);
            }

            // SET型の場合
            if (preg_match('/^set\((.*)\)$/i', $columnType, $matches)) {
                $values = str_getcsv($matches[1], ',', "'");

                return array_map('trim', $values);
            }
        } catch (\Exception $e) {
            // エラーの場合は空配列を返す
        }

        return [];
    }

    /**
     * カラムの制約情報を取得
     */
    private function getColumnConstraints(string $tableName, string $columnName): array
    {
        $constraints = [];

        try {
            // カラムの詳細情報を取得
            $result = DB::select('
                SELECT
                    COLUMN_TYPE,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE,
                    CHARACTER_MAXIMUM_LENGTH,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    EXTRA
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ', [env('DB_DATABASE'), $tableName, $columnName]);

            if (empty($result)) {
                return [];
            }

            $columnInfo = $result[0];
            $columnType = $columnInfo->COLUMN_TYPE;

            // 数値型の範囲を取得
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/i', $columnType)) {
                $isUnsigned = stripos($columnType, 'unsigned') !== false;

                $ranges = [
                    'tinyint' => ['min' => -128, 'max' => 127, 'unsigned_max' => 255],
                    'smallint' => ['min' => -32768, 'max' => 32767, 'unsigned_max' => 65535],
                    'mediumint' => ['min' => -8388608, 'max' => 8388607, 'unsigned_max' => 16777215],
                    'int' => ['min' => -2147483648, 'max' => 2147483647, 'unsigned_max' => 4294967295],
                    'bigint' => ['min' => '-9223372036854775808', 'max' => '9223372036854775807', 'unsigned_max' => '18446744073709551615'],
                ];

                preg_match('/^(\w+)/', $columnType, $typeMatch);
                $baseType = strtolower($typeMatch[1]);

                if (isset($ranges[$baseType])) {
                    if ($isUnsigned) {
                        $constraints['min_value'] = 0;
                        $constraints['max_value'] = $ranges[$baseType]['unsigned_max'];
                    } else {
                        $constraints['min_value'] = $ranges[$baseType]['min'];
                        $constraints['max_value'] = $ranges[$baseType]['max'];
                    }
                }
            }

            // DECIMAL型の精度
            if (preg_match('/^decimal\((\d+),(\d+)\)/i', $columnType, $matches)) {
                $precision = (int) $matches[1];
                $scale = (int) $matches[2];
                $maxValue = str_repeat('9', $precision - $scale).'.'.str_repeat('9', $scale);
                $constraints['max_value'] = $maxValue;
                $constraints['min_value'] = '-'.$maxValue;
                $constraints['precision'] = $precision;
                $constraints['scale'] = $scale;
            }

            // 文字列の最大長
            if ($columnInfo->CHARACTER_MAXIMUM_LENGTH) {
                $constraints['max_length'] = $columnInfo->CHARACTER_MAXIMUM_LENGTH;
            }

            // CHECK制約を取得（MySQL 8.0.16以降）
            $checkConstraints = $this->getCheckConstraints($tableName, $columnName);
            if (! empty($checkConstraints)) {
                $constraints['check_constraints'] = $checkConstraints;
            }

        } catch (\Exception $e) {
            // エラーの場合は空配列を返す
        }

        return $constraints;
    }

    /**
     * CHECK制約を取得
     */
    private function getCheckConstraints(string $tableName, string $columnName): array
    {
        try {
            // MySQL 8.0.16以降でCHECK制約をサポート
            $result = DB::select('
                SELECT
                    cc.CONSTRAINT_NAME,
                    cc.CHECK_CLAUSE
                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS cc
                JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                    ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                    AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                WHERE tc.TABLE_SCHEMA = ?
                AND tc.TABLE_NAME = ?
                AND cc.CHECK_CLAUSE LIKE ?
            ', [env('DB_DATABASE'), $tableName, '%'.$columnName.'%']);

            $constraints = [];
            foreach ($result as $row) {
                $constraints[] = [
                    'name' => $row->CONSTRAINT_NAME,
                    'clause' => $row->CHECK_CLAUSE,
                ];
            }

            return $constraints;
        } catch (\Exception $e) {
            // CHECK制約がサポートされていない場合
            return [];
        }
    }

    /**
     * インデックス情報を取得
     */
    private function getIndexesInfo(string $tableName): array
    {
        $rows = DB::select('
            SELECT
                INDEX_NAME,
                COLUMN_NAME,
                NON_UNIQUE,
                SEQ_IN_INDEX
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ', [env('DB_DATABASE'), $tableName]);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->INDEX_NAME]['columns'][] = $row->COLUMN_NAME;
            $grouped[$row->INDEX_NAME]['non_unique'] = $row->NON_UNIQUE;
        }

        $indexesInfo = [];
        foreach ($grouped as $indexName => $data) {
            $indexesInfo[] = [
                'name' => $indexName,
                'columns' => $data['columns'],
                'is_unique' => $data['non_unique'] == 0,
                'is_primary' => $indexName === 'PRIMARY',
            ];
        }

        return $indexesInfo;
    }

    /**
     * 外部キー情報を取得
     */
    private function getForeignKeysInfo(string $tableName): array
    {
        $rows = DB::select('
            SELECT
                kcu.CONSTRAINT_NAME,
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ?
            AND kcu.TABLE_NAME = ?
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
        ', [env('DB_DATABASE'), $tableName]);

        $grouped = [];
        foreach ($rows as $row) {
            $name = $row->CONSTRAINT_NAME;
            if (! isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'foreign_table' => $row->REFERENCED_TABLE_NAME,
                    'foreign_columns' => [],
                    'on_delete' => $row->DELETE_RULE,
                    'on_update' => $row->UPDATE_RULE,
                ];
            }
            $grouped[$name]['columns'][] = $row->COLUMN_NAME;
            $grouped[$name]['foreign_columns'][] = $row->REFERENCED_COLUMN_NAME;
        }

        return array_values($grouped);
    }

    /**
     * プライマリーキーを取得
     */
    private function getPrimaryKey(string $tableName): ?array
    {
        $rows = DB::select('
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND INDEX_NAME = \'PRIMARY\'
            ORDER BY SEQ_IN_INDEX
        ', [env('DB_DATABASE'), $tableName]);

        if (empty($rows)) {
            return null;
        }

        return array_map(fn ($row) => $row->COLUMN_NAME, $rows);
    }

    /**
     * テーブルコメントを取得
     */
    private function getTableComment(string $tableName): ?string
    {
        $result = DB::select(
            'SELECT table_comment FROM information_schema.tables
             WHERE table_schema = ? AND table_name = ?',
            [env('DB_DATABASE'), $tableName]
        );

        return $result[0]->table_comment ?? null;
    }

    /**
     * テーブルエンジンを取得
     */
    private function getTableEngine(string $tableName): ?string
    {
        $result = DB::select(
            'SELECT engine FROM information_schema.tables
             WHERE table_schema = ? AND table_name = ?',
            [env('DB_DATABASE'), $tableName]
        );

        return $result[0]->engine ?? null;
    }

    /**
     * テーブルの照合順序を取得
     */
    private function getTableCollation(string $tableName): ?string
    {
        $result = DB::select(
            'SELECT table_collation FROM information_schema.tables
             WHERE table_schema = ? AND table_name = ?',
            [env('DB_DATABASE'), $tableName]
        );

        return $result[0]->table_collation ?? null;
    }

}
