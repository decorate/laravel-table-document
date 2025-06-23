<?php

namespace Decorate\LaravelTableDocument\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class TableMetadataService
{
    private array $metadata = [];

    private string $metadataPath;

    public function __construct()
    {
        $this->metadataPath = config('table-document.metadata.path');
        $this->loadMetadata();
    }

    /**
     * メタデータファイルを読み込む
     */
    private function loadMetadata(): void
    {
        if (File::exists($this->metadataPath)) {
            $content = File::get($this->metadataPath);
            $this->metadata = Yaml::parse($content) ?? [];
        }
    }

    /**
     * メタデータファイルをリロード
     */
    public function reload(): void
    {
        $this->loadMetadata();
    }

    /**
     * テーブルの論理名を取得
     */
    public function getTableLogicalName(string $tableName): ?string
    {
        return $this->metadata['tables'][$tableName]['logical_name'] ?? null;
    }

    /**
     * テーブルの説明を取得
     */
    public function getTableDescription(string $tableName): ?string
    {
        return $this->metadata['tables'][$tableName]['description'] ?? null;
    }

    /**
     * カラムの論理名を取得
     */
    public function getColumnLogicalName(string $tableName, string $columnName): ?string
    {
        // テーブル固有の定義を優先
        $logicalName = $this->metadata['tables'][$tableName]['columns'][$columnName]['logical_name'] ?? null;

        // 共通カラム定義を確認
        if (! $logicalName && isset($this->metadata['settings']['common_columns'][$columnName])) {
            $logicalName = $this->metadata['settings']['common_columns'][$columnName]['logical_name'];
        }

        return $logicalName;
    }

    /**
     * カラムの説明を取得
     */
    public function getColumnDescription(string $tableName, string $columnName): ?string
    {
        // テーブル固有の定義を優先
        $description = $this->metadata['tables'][$tableName]['columns'][$columnName]['description'] ?? null;

        // 共通カラム定義を確認
        if (! $description && isset($this->metadata['settings']['common_columns'][$columnName])) {
            $description = $this->metadata['settings']['common_columns'][$columnName]['description'];
        }

        return $description;
    }

    /**
     * ENUM値のラベルを取得
     */
    public function getEnumLabels(string $tableName, string $columnName): array
    {
        return $this->metadata['tables'][$tableName]['columns'][$columnName]['enum_labels'] ?? [];
    }

    /**
     * Boolean値のラベルを取得
     */
    public function getBooleanLabels(string $tableName, string $columnName): array
    {
        return $this->metadata['tables'][$tableName]['columns'][$columnName]['boolean_labels'] ?? [
            'true' => '有効',
            'false' => '無効',
        ];
    }

    /**
     * カラムの制約情報を取得
     */
    public function getColumnConstraints(string $tableName, string $columnName): array
    {
        return $this->metadata['tables'][$tableName]['columns'][$columnName]['constraints'] ?? [];
    }

    /**
     * カラムの参照情報を取得
     */
    public function getColumnReference(string $tableName, string $columnName): ?array
    {
        return $this->metadata['tables'][$tableName]['columns'][$columnName]['references'] ?? null;
    }

    /**
     * データ型の日本語ラベルを取得
     */
    public function getTypeLabel(string $type): string
    {
        // 基本型を抽出（例: varchar(100) → varchar）
        $baseType = preg_replace('/\(.*\)/', '', strtolower($type));

        return $this->metadata['settings']['type_labels'][$baseType] ?? $type;
    }

    /**
     * メタデータを統合してテーブル情報を拡張
     */
    public function enrichTableInfo(array $tableInfo): array
    {
        $tableName = $tableInfo['name'];

        // テーブル情報を拡張
        $tableInfo['logical_name'] = $this->getTableLogicalName($tableName);
        $tableInfo['description'] = $this->getTableDescription($tableName);

        // 論理名と説明が空の場合は、元のcommentをパース（後方互換性のため）
        if (empty($tableInfo['logical_name']) && empty($tableInfo['description']) && ! empty($tableInfo['comment'])) {
            $parsed = $this->parseComment($tableInfo['comment']);
            if (empty($tableInfo['logical_name'])) {
                $tableInfo['logical_name'] = $parsed['logical_name'];
            }
            if (empty($tableInfo['description'])) {
                $tableInfo['description'] = $parsed['description'];
            }
        }

        // カラム情報を拡張
        foreach ($tableInfo['columns'] as &$column) {
            $columnName = $column['name'];

            // 論理名と説明
            $column['logical_name'] = $this->getColumnLogicalName($tableName, $columnName);
            $column['description'] = $this->getColumnDescription($tableName, $columnName);

            // 論理名と説明が空の場合は、元のcommentをパース（後方互換性のため）
            if (empty($column['logical_name']) && empty($column['description']) && ! empty($column['comment'])) {
                $parsed = $this->parseComment($column['comment']);
                if (empty($column['logical_name'])) {
                    $column['logical_name'] = $parsed['logical_name'];
                }
                if (empty($column['description'])) {
                    $column['description'] = $parsed['description'];
                }
            }

            // ENUM値のラベル
            if (! empty($column['enum_values'])) {
                $enumLabels = $this->getEnumLabels($tableName, $columnName);
                if (! empty($enumLabels)) {
                    $column['enum_labels'] = $enumLabels;
                }
            }

            // Boolean値のラベル
            if ($column['type'] === 'boolean' || $column['type'] === 'tinyint') {
                $column['boolean_labels'] = $this->getBooleanLabels($tableName, $columnName);
            }

            // メタデータからの制約情報
            $metaConstraints = $this->getColumnConstraints($tableName, $columnName);
            if (! empty($metaConstraints)) {
                $column['constraints'] = array_merge($column['constraints'] ?? [], $metaConstraints);
            }

            // 参照情報
            $reference = $this->getColumnReference($tableName, $columnName);
            if ($reference) {
                $column['reference'] = $reference;
            }

            // 型の日本語表記
            $column['type_label'] = $this->getTypeLabel($column['type']);
        }

        return $tableInfo;
    }

    /**
     * サンプルメタデータファイルを生成（既存のものがない場合のみ）
     *
     * @param  bool  $force  強制的に新規作成
     */
    public function generateSampleMetadata(array $tables, bool $force = false): void
    {
        if (! $force && File::exists($this->metadataPath)) {
            // 既存ファイルがある場合は updateMetadata を使用
            $this->updateMetadata($tables);

            return;
        }

        $metadata = [
            'tables' => [],
            'settings' => $this->getDefaultSettings(),
        ];

        foreach ($tables as $table) {
            // テーブルコメントを解析
            $tableParsed = $this->parseComment($table['comment']);

            $tableMetadata = [
                'logical_name' => $tableParsed['logical_name'],
                'description' => $tableParsed['description'],
                'columns' => [],
            ];

            foreach ($table['columns'] as $column) {
                // カラムコメントを解析
                $columnParsed = $this->parseComment($column['comment']);

                $columnMetadata = [
                    'logical_name' => $columnParsed['logical_name'],
                    'description' => $columnParsed['description'],
                ];

                // ENUM値がある場合
                if (! empty($column['enum_values'])) {
                    $columnMetadata['enum_labels'] = [];
                    foreach ($column['enum_values'] as $value) {
                        $columnMetadata['enum_labels'][$value] = $value;
                    }
                }

                $tableMetadata['columns'][$column['name']] = $columnMetadata;
            }

            $metadata['tables'][$table['name']] = $tableMetadata;
        }

        $yaml = Yaml::dump($metadata, 6, 2);
        File::put($this->metadataPath, $yaml);
    }

    /**
     * メタデータファイルが存在するか
     */
    public function exists(): bool
    {
        return File::exists($this->metadataPath);
    }

    /**
     * 既存のデータを保持しながらメタデータファイルを更新
     *
     * @param  array  $tables  現在のテーブル構造
     * @param  bool  $backup  バックアップを作成するか
     * @return array 更新結果
     */
    public function updateMetadata(array $tables, bool $backup = true): array
    {
        // バックアップの作成
        if ($backup && File::exists($this->metadataPath)) {
            $backupPath = $this->metadataPath.'.backup.'.date('YmdHis');
            File::copy($this->metadataPath, $backupPath);
        }

        // 既存のメタデータを読み込む
        $existingMetadata = $this->metadata;

        // 新しいメタデータ構造を作成（既存の値を保持）
        $newMetadata = [
            'tables' => [],
            'settings' => $existingMetadata['settings'] ?? $this->getDefaultSettings(),
        ];

        $stats = [
            'new_tables' => 0,
            'new_columns' => 0,
            'removed_tables' => 0,
            'removed_columns' => 0,
            'preserved_items' => 0,
        ];

        // 現在のテーブル構造をマージ
        foreach ($tables as $table) {
            $tableName = $table['name'];
            $existingTable = $existingMetadata['tables'][$tableName] ?? null;

            // テーブルコメントを解析
            $tableParsed = $this->parseComment($table['comment']);

            if ($existingTable) {
                // 既存のテーブル情報を保持
                $newMetadata['tables'][$tableName] = [
                    'logical_name' => $existingTable['logical_name'],
                    'description' => $existingTable['description'],
                    'columns' => [],
                ];
                $stats['preserved_items']++;
            } else {
                // 新しいテーブル
                $newMetadata['tables'][$tableName] = [
                    'logical_name' => $tableParsed['logical_name'],
                    'description' => $tableParsed['description'],
                    'columns' => [],
                ];
                $stats['new_tables']++;
            }

            // カラム情報をマージ
            foreach ($table['columns'] as $column) {
                $columnName = $column['name'];
                $existingColumn = $existingTable['columns'][$columnName] ?? null;

                if ($existingColumn) {
                    // 既存のカラム情報を保持
                    $newMetadata['tables'][$tableName]['columns'][$columnName] = $existingColumn;
                    $stats['preserved_items']++;
                } else {
                    // 新しいカラム - コメントを解析
                    $columnParsed = $this->parseComment($column['comment']);

                    $columnMetadata = [
                        'logical_name' => $columnParsed['logical_name'],
                        'description' => $columnParsed['description'],
                    ];

                    // ENUM値がある場合
                    if (! empty($column['enum_values'])) {
                        $columnMetadata['enum_labels'] = [];
                        foreach ($column['enum_values'] as $value) {
                            $columnMetadata['enum_labels'][$value] = $value;
                        }
                    }

                    $newMetadata['tables'][$tableName]['columns'][$columnName] = $columnMetadata;
                    $stats['new_columns']++;
                }
            }

            // 削除されたカラムを検出（オプション：コメントアウトして保持）
            if ($existingTable) {
                foreach ($existingTable['columns'] as $oldColumnName => $oldColumnData) {
                    $stillExists = false;
                    foreach ($table['columns'] as $column) {
                        if ($column['name'] === $oldColumnName) {
                            $stillExists = true;
                            break;
                        }
                    }

                    if (! $stillExists) {
                        // 削除されたカラムをコメント付きで保持
                        $newMetadata['tables'][$tableName]['columns']['_removed_'.$oldColumnName] = array_merge(
                            $oldColumnData,
                            ['_removed_at' => date('Y-m-d H:i:s'), '_status' => 'removed']
                        );
                        $stats['removed_columns']++;
                    }
                }
            }
        }

        // 削除されたテーブルを検出
        if (isset($existingMetadata['tables'])) {
            foreach ($existingMetadata['tables'] as $oldTableName => $oldTableData) {
                $stillExists = false;
                foreach ($tables as $table) {
                    if ($table['name'] === $oldTableName) {
                        $stillExists = true;
                        break;
                    }
                }

                if (! $stillExists && ! str_starts_with($oldTableName, '_removed_')) {
                    // 削除されたテーブルをコメント付きで保持
                    $newMetadata['tables']['_removed_'.$oldTableName] = array_merge(
                        $oldTableData,
                        ['_removed_at' => date('Y-m-d H:i:s'), '_status' => 'removed']
                    );
                    $stats['removed_tables']++;
                }
            }
        }

        // YAMLファイルに保存
        $yaml = Yaml::dump($newMetadata, 6, 2);
        File::put($this->metadataPath, $yaml);

        // メタデータを再読み込み
        $this->loadMetadata();

        return $stats;
    }

    /**
     * デフォルトの設定を取得
     */
    private function getDefaultSettings(): array
    {
        return [
            'common_columns' => [
                'created_at' => [
                    'logical_name' => '作成日時',
                    'description' => 'レコードが作成された日時',
                ],
                'updated_at' => [
                    'logical_name' => '更新日時',
                    'description' => 'レコードが最後に更新された日時',
                ],
                'deleted_at' => [
                    'logical_name' => '削除日時',
                    'description' => '論理削除された日時',
                ],
            ],
            'type_labels' => [
                //                'bigint' => '整数（大）',
                //                'int' => '整数',
                //                'smallint' => '整数（小）',
                //                'tinyint' => '整数（極小）',
                //                'varchar' => '文字列',
                //                'text' => '長文',
                //                'decimal' => '小数',
                //                'float' => '浮動小数',
                //                'double' => '倍精度浮動小数',
                //                'datetime' => '日時',
                //                'date' => '日付',
                //                'time' => '時刻',
                //                'timestamp' => 'タイムスタンプ',
                //                'boolean' => '真偽値',
                //                'json' => 'JSON',
                //                'enum' => '選択肢',
                //                'set' => '複数選択'
            ],
        ];
    }

    /**
     * 削除されたアイテムをクリーンアップ
     */
    public function cleanupRemovedItems(): int
    {
        $count = 0;

        if (! isset($this->metadata['tables'])) {
            return $count;
        }

        $cleaned = $this->metadata;

        // 削除されたテーブルを除去
        foreach ($cleaned['tables'] as $tableName => $tableData) {
            if (str_starts_with($tableName, '_removed_')) {
                unset($cleaned['tables'][$tableName]);
                $count++;

                continue;
            }

            // 削除されたカラムを除去
            if (isset($tableData['columns'])) {
                foreach ($tableData['columns'] as $columnName => $columnData) {
                    if (str_starts_with($columnName, '_removed_')) {
                        unset($cleaned['tables'][$tableName]['columns'][$columnName]);
                        $count++;
                    }
                }
            }
        }

        if ($count > 0) {
            $yaml = Yaml::dump($cleaned, 6, 2);
            File::put($this->metadataPath, $yaml);
            $this->loadMetadata();
        }

        return $count;
    }

    /**
     * メタデータの差分を取得
     */
    public function getDiff(array $currentTables): array
    {
        $diff = [
            'new_tables' => [],
            'removed_tables' => [],
            'modified_tables' => [],
        ];

        $existingTables = array_keys($this->metadata['tables'] ?? []);
        $currentTableNames = array_column($currentTables, 'name');

        // 新しいテーブル
        $diff['new_tables'] = array_diff($currentTableNames, $existingTables);

        // 削除されたテーブル（_removed_プレフィックスを除外）
        $diff['removed_tables'] = array_filter(
            array_diff($existingTables, $currentTableNames),
            fn ($name) => ! str_starts_with($name, '_removed_')
        );

        // 変更されたテーブル（カラムの追加・削除をチェック）
        foreach ($currentTables as $table) {
            $tableName = $table['name'];
            if (! isset($this->metadata['tables'][$tableName])) {
                continue;
            }

            $existingColumns = array_keys($this->metadata['tables'][$tableName]['columns'] ?? []);
            $currentColumns = array_column($table['columns'], 'name');

            $newColumns = array_diff($currentColumns, $existingColumns);
            $removedColumns = array_filter(
                array_diff($existingColumns, $currentColumns),
                fn ($name) => ! str_starts_with($name, '_removed_')
            );

            if (! empty($newColumns) || ! empty($removedColumns)) {
                $diff['modified_tables'][$tableName] = [
                    'new_columns' => $newColumns,
                    'removed_columns' => $removedColumns,
                ];
            }
        }

        return $diff;
    }

    /**
     * コメントから論理名と説明を解析
     *
     * @return array ['logical_name' => string, 'description' => string]
     */
    private function parseComment(?string $comment): array
    {
        // コメントがない場合は空文字列を返す
        if (empty($comment)) {
            return [
                'logical_name' => '',
                'description' => '',
            ];
        }

        // | で分割
        $parts = explode('|', $comment, 2);

        if (count($parts) === 2) {
            // | がある場合：左側が論理名、右側が説明
            return [
                'logical_name' => trim($parts[0]),
                'description' => trim($parts[1]),
            ];
        } else {
            // | がない場合：全体を論理名とする
            return [
                'logical_name' => trim($comment),
                'description' => '',
            ];
        }
    }
}
