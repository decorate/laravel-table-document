<?php

namespace Decorate\LaravelTableDocument\Commands;

use Decorate\LaravelTableDocument\Services\TableDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateTableDocCommand extends Command
{
    protected $signature = 'table:doc
                            {--format=html : 出力フォーマット (html, json, static-html)}
                            {--output= : 出力ファイルパス}
                            {--table= : 特定のテーブルのみ出力}
                            {--force : 既存ファイルを上書き}
                            {--generate-metadata : メタデータファイルを生成/更新}
                            {--update-metadata : メタデータファイルを更新（既存の情報を保持）}
                            {--check-diff : データベースとメタデータの差分をチェック}
                            {--cleanup-removed : 削除されたテーブル/カラムの情報をクリーンアップ}
                            {--backup : メタデータ更新時にバックアップを作成}';

    protected $description = 'データベースのテーブル定義書を生成';

    private TableDocumentService $service;

    public function __construct(TableDocumentService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        // メタデータの差分チェックモード
        if ($this->option('check-diff')) {
            return $this->checkDiff();
        }

        // 削除されたアイテムのクリーンアップ
        if ($this->option('cleanup-removed')) {
            return $this->cleanupRemoved();
        }

        // メタデータファイル生成/更新モード
        if ($this->option('generate-metadata') || $this->option('update-metadata')) {
            return $this->handleMetadata();
        }

        $format = $this->option('format');
        $output = $this->option('output');
        $tableName = $this->option('table');
        $force = $this->option('force');

        // static-htmlフォーマットの場合は専用メソッドを使用
        if ($format === 'static-html') {
            return $this->generateStaticHtml($force);
        }

        $this->info('テーブル定義書を生成中...');

        if ($tableName) {
            $data = [$this->service->getTableInfo($tableName)];
        } else {
            $data = $this->service->getAllTablesInfo();
        }

        $content = match ($format) {
            'json' => $this->generateJson($data),
            default => $this->generateHtml($data),
        };

        if ($output) {
            // 既存ファイルのチェック
            if (File::exists($output) && ! $force) {
                if (! $this->confirm("ファイル '{$output}' は既に存在します。上書きしますか？")) {
                    $this->info('処理をキャンセルしました。');

                    return 1;
                }
            }

            // ディレクトリが存在しない場合は作成
            $directory = dirname($output);
            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($output, $content);
            $this->info("ファイルを生成しました: {$output}");
        } else {
            $this->line($content);
        }

        return 0;
    }

    /**
     * メタデータの差分をチェック
     */
    private function checkDiff(): int
    {
        $this->info('データベースとメタデータの差分をチェック中...');

        $tables = $this->service->getAllTablesInfo();
        $diff = $this->service->metadataService->getDiff($tables);

        // 新しいテーブル
        if (! empty($diff['new_tables'])) {
            $this->info("\n📋 新しいテーブル:");
            foreach ($diff['new_tables'] as $tableName) {
                $this->line("  + {$tableName}");
            }
        }

        // 削除されたテーブル
        if (! empty($diff['removed_tables'])) {
            $this->warn("\n🗑️  削除されたテーブル:");
            foreach ($diff['removed_tables'] as $tableName) {
                $this->line("  - {$tableName}");
            }
        }

        // 変更されたテーブル
        if (! empty($diff['modified_tables'])) {
            $this->info("\n✏️  変更されたテーブル:");
            foreach ($diff['modified_tables'] as $tableName => $changes) {
                $this->line("  * {$tableName}");

                if (! empty($changes['new_columns'])) {
                    foreach ($changes['new_columns'] as $column) {
                        $this->line("    + {$column}");
                    }
                }

                if (! empty($changes['removed_columns'])) {
                    foreach ($changes['removed_columns'] as $column) {
                        $this->line("    - {$column}");
                    }
                }
            }
        }

        // 変更がない場合
        if (empty($diff['new_tables']) && empty($diff['removed_tables']) && empty($diff['modified_tables'])) {
            $this->info("\n✅ データベースとメタデータは同期されています。");
        } else {
            $this->newLine();
            $this->info('メタデータを更新するには以下のコマンドを実行してください:');
            $this->line('  php artisan table:doc --update-metadata');
        }

        return 0;
    }

    /**
     * 削除されたアイテムをクリーンアップ
     */
    private function cleanupRemoved(): int
    {
        if (! $this->confirm('削除されたテーブル/カラムの情報を完全に削除しますか？')) {
            $this->info('処理をキャンセルしました。');

            return 1;
        }

        $count = $this->service->metadataService->cleanupRemovedItems();

        if ($count > 0) {
            $this->info("✅ {$count}個のアイテムをクリーンアップしました。");
        } else {
            $this->info('クリーンアップするアイテムはありませんでした。');
        }

        return 0;
    }

    /**
     * メタデータファイルの生成/更新を処理
     */
    private function handleMetadata(): int
    {
        $metadataPath = config('table-document.metadata.path');
        $isUpdate = $this->option('update-metadata');
        $backup = $this->option('backup') ?? true;

        if (! $isUpdate && File::exists($metadataPath) && ! $this->option('force')) {
            $this->warn('メタデータファイルが既に存在します。');
            $choice = $this->choice(
                '何をしますか？',
                [
                    'update' => '既存の情報を保持しながら更新',
                    'overwrite' => '完全に上書き（既存の情報は失われます）',
                    'cancel' => 'キャンセル',
                ],
                'update'
            );

            if ($choice === 'cancel') {
                $this->info('処理をキャンセルしました。');

                return 1;
            }

            $isUpdate = ($choice === 'update');
        }

        $this->info('メタデータファイルを'.($isUpdate ? '更新' : '生成').'中...');

        try {
            $tables = $this->service->getAllTablesInfo();

            if ($isUpdate || (File::exists($metadataPath) && ! $this->option('force'))) {
                // 既存データを保持しながら更新
                $stats = $this->service->metadataService->updateMetadata($tables, $backup);

                $this->info('✅ メタデータファイルを更新しました。');
                $this->info('📁 保存先: '.$metadataPath);

                if ($backup && File::exists($metadataPath.'.backup.'.date('YmdHis'))) {
                    $this->info('💾 バックアップを作成しました。');
                }

                $this->newLine();
                $this->info('更新結果:');
                $this->table(
                    ['項目', '件数'],
                    [
                        ['新しいテーブル', $stats['new_tables']],
                        ['新しいカラム', $stats['new_columns']],
                        ['削除されたテーブル', $stats['removed_tables']],
                        ['削除されたカラム', $stats['removed_columns']],
                        ['保持された項目', $stats['preserved_items']],
                    ]
                );

                if ($stats['removed_tables'] > 0 || $stats['removed_columns'] > 0) {
                    $this->newLine();
                    $this->warn('削除されたテーブル/カラムは "_removed_" プレフィックス付きで保持されています。');
                    $this->info('完全に削除するには以下のコマンドを実行してください:');
                    $this->line('  php artisan table:doc --cleanup-removed');
                }
            } else {
                // 新規生成
                $this->service->metadataService->generateSampleMetadata($tables, true);

                $this->info('✅ メタデータファイルを生成しました。');
                $this->info('📁 保存先: '.$metadataPath);
            }

            $this->newLine();
            $this->info('生成されたファイルを編集して、以下の情報を設定できます：');
            $this->info('  - テーブルとカラムの論理名');
            $this->info('  - 詳細な説明');
            $this->info('  - ENUM値の日本語ラベル');
            $this->info('  - 制約情報');
            $this->info('  - 参照関係');
            $this->newLine();
            $this->info('編集後、再度HTMLを生成すると反映されます：');
            $this->info('  php artisan table:doc --format=static-html --force');

            return 0;
        } catch (\Exception $e) {
            $this->error('エラーが発生しました: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * 静的HTMLを生成
     */
    private function generateStaticHtml(bool $force): int
    {
        $this->info('静的HTMLファイルを生成中...');

        // プログレスバーの表示
        $progressBar = $this->output->createProgressBar(4);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $progressBar->setMessage('HTMLファイルの生成準備中...');
        $progressBar->start();

        try {
            $result = $this->service->generateHtmlFile($force);

            $progressBar->setMessage('テーブル情報を取得中...');
            $progressBar->advance();

            if ($result['success']) {
                $progressBar->setMessage('HTMLファイルを生成中...');
                $progressBar->advance();

                $progressBar->setMessage('メタデータを保存中...');
                $progressBar->advance();

                $progressBar->setMessage('完了！');
                $progressBar->finish();

                $this->newLine(2);
                $this->info('✅ '.$result['message']);
                $this->info('📁 保存先: '.$result['path']);
                $this->info('🌐 URL: '.$result['url']);
                $this->info('📅 生成日時: '.$result['generated_at']);

                // ファイルサイズの表示
                if (File::exists($result['path'])) {
                    $size = File::size($result['path']);
                    $this->info('📊 ファイルサイズ: '.$this->formatBytes($size));
                }

                return 0;
            } else {
                $progressBar->finish();
                $this->newLine(2);
                $this->error('HTMLファイルの生成に失敗しました。');

                return 1;
            }
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error('エラーが発生しました: '.$e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * バイト数を人間が読みやすい形式に変換
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * メタデータファイルを生成
     */
    private function generateMetadata(): int
    {
        $metadataPath = config('table-document.metadata.path');

        if (File::exists($metadataPath) && ! $this->option('force')) {
            if (! $this->confirm('メタデータファイルが既に存在します。上書きしますか？')) {
                $this->info('処理をキャンセルしました。');

                return 1;
            }
        }

        $this->info('メタデータファイルを生成中...');

        try {
            $this->service->generateMetadataFile();

            $this->info('✅ メタデータファイルを生成しました。');
            $this->info('📁 保存先: '.$metadataPath);
            $this->newLine();
            $this->info('生成されたファイルを編集して、以下の情報を設定できます：');
            $this->info('  - テーブルとカラムの論理名');
            $this->info('  - 詳細な説明');
            $this->info('  - ENUM値の日本語ラベル');
            $this->info('  - 制約情報');
            $this->info('  - 参照関係');
            $this->newLine();
            $this->info('編集後、再度HTMLを生成すると反映されます：');
            $this->info('  php artisan table:doc --format=static-html --force');

            return 0;
        } catch (\Exception $e) {
            $this->error('エラーが発生しました: '.$e->getMessage());

            return 1;
        }
    }

    private function generateJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function generateHtml(array $data): string
    {
        // 静的HTML用のテンプレートを使用
        return view('table-document::static', [
            'tables' => $data,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'database' => env('DB_DATABASE'),
        ])->render();
    }
}
