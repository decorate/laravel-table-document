# Laravel Table Document Generator

Laravelアプリケーションのデータベーステーブル定義書を自動生成するパッケージです。

## 特徴

- データベースから直接テーブル情報を取得して定義書を生成
- HTML、JSONでの出力に対応
- YAMLベースのメタデータファイルで論理名や説明を管理
- ENUM値の日本語ラベル設定
- 制約情報（最大値・最小値・文字数制限など）の表示
- 外部キー関係の可視化
- 静的HTMLファイルとして保存・共有可能

## 要件

- PHP 8.1以上
- Laravel 10.0以上

## インストール

```bash
composer require decorate/laravel-table-document
```

設定ファイルを公開する場合：

```bash
php artisan vendor:publish --tag=table-document-config
```

## 使い方

### 基本的な使用方法

テーブル定義書をHTMLファイルとして生成：

```bash
php artisan table:doc --format=static-html
```

### メタデータの使用

1. メタデータファイルを生成：

```bash
php artisan table:doc --generate-metadata
```

2. 生成された `config/table-metadata.yaml` を編集して、論理名や説明を追加

3. HTMLを再生成：

```bash
php artisan table:doc --format=static-html --force
```

### その他のオプション

```bash
# JSON形式で出力
php artisan table:doc --format=json --output=tables.json

# 特定のテーブルのみ出力
php artisan table:doc --table=users

# メタデータとデータベースの差分をチェック
php artisan table:doc --check-diff

# メタデータを更新（既存の情報を保持）
php artisan table:doc --update-metadata

# 削除されたテーブル/カラムの情報をクリーンアップ
php artisan table:doc --cleanup-removed
```

## 設定

`config/table-document.php` で以下の設定が可能です：

```php
return [
    'output' => [
        // 静的HTMLファイルの保存先ディレクトリ
        'directory' => 'table-documents',
        
        // デフォルトの出力形式
        'default_format' => 'static-html',
    ],
    
    'metadata' => [
        // メタデータファイルのパス
        'path' => config_path('table-metadata.yaml'),
        
        // メタデータの自動生成
        'auto_generate' => false,
    ],
    
    'database' => [
        // 除外するテーブル
        'exclude_tables' => [
            'migrations',
            'password_resets',
            'failed_jobs',
        ],
    ]
];
```

## メタデータファイルの構造

```yaml
tables:
  users:
    logical_name: ユーザー
    description: システムのユーザー情報を管理するテーブル
    columns:
      id:
        logical_name: ID
        description: ユーザーの一意識別子
      email:
        logical_name: メールアドレス
        description: ログインに使用するメールアドレス
      status:
        logical_name: ステータス
        description: ユーザーの状態
        enum_labels:
          active: 有効
          inactive: 無効
          suspended: 停止中

settings:
  common_columns:
    created_at:
      logical_name: 作成日時
      description: レコードが作成された日時
    updated_at:
      logical_name: 更新日時
      description: レコードが最後に更新された日時
```

## Migrationの書き方

より充実した定義書を生成するためのMigrationファイルの書き方は以下を参照してください。

- [Migrationファイルの書き方ガイド](documents/exsample.md)

## ライセンス

MIT License

## 作者

- take (ishizuka@shrp.jp)
