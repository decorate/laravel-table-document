<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Table Document Generator Configuration
  |--------------------------------------------------------------------------
  */

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
      'password_reset_tokens',
      'failed_jobs',
      'personal_access_tokens',
    ],

    // 除外するカラム（全テーブル共通）
    'exclude_columns' => [
      // 'remember_token',
    ],
  ]
];
