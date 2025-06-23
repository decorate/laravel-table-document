<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $database }} - テーブル定義書</title>
  <style>
    /* リセット */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      background-color: #f5f5f5;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    .header {
      background-color: #fff;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .header.has-metadata {
      border-top: 4px solid #3498db;
    }

    .header h1 {
      font-size: 2.5rem;
      margin-bottom: 10px;
      color: #2c3e50;
    }

    .meta-info {
      color: #666;
      font-size: 0.9rem;
    }

    .meta-info p {
      margin: 5px 0;
    }

    /* テーブル一覧 */
    .table-list {
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .table-list h2 {
      font-size: 1.8rem;
      margin-bottom: 20px;
      color: #2c3e50;
    }

    .table-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 15px;
    }

    .table-card {
      border: 1px solid #ddd;
      padding: 15px;
      border-radius: 6px;
      transition: background-color 0.2s;
    }

    .table-card:hover {
      background-color: #f8f9fa;
    }

    .table-card a {
      text-decoration: none;
      color: #3498db;
    }

    .table-card h3 {
      font-size: 1.2rem;
      margin-bottom: 5px;
    }

    .table-card p {
      color: #666;
      font-size: 0.9rem;
    }

    /* テーブル詳細 */
    .table-detail {
      background-color: #fff;
      margin-bottom: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .table-header {
      background-color: #ecf0f1;
      padding: 20px;
      border-bottom: 1px solid #ddd;
    }

    .table-header h2 {
      font-size: 1.8rem;
      color: #2c3e50;
      margin-bottom: 10px;
    }

    .table-meta {
      color: #666;
      font-size: 0.9rem;
    }

    .table-meta span {
      margin-right: 20px;
    }

    .table-section {
      padding: 20px;
    }

    .table-section h3 {
      font-size: 1.3rem;
      margin-bottom: 15px;
      color: #34495e;
    }

    /* データテーブル */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .data-table th {
      background-color: #f8f9fa;
      padding: 10px;
      text-align: left;
      font-weight: 600;
      border-bottom: 2px solid #dee2e6;
    }

    .data-table td {
      padding: 10px;
      border-bottom: 1px solid #dee2e6;
    }

    .data-table tr:hover {
      background-color: #f8f9fa;
    }

    /* バッジ */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      font-size: 0.75rem;
      font-weight: 600;
      border-radius: 4px;
      margin-left: 5px;
    }

    .badge-primary {
      background-color: #ffc107;
      color: #333;
    }

    .badge-info {
      background-color: #17a2b8;
      color: #fff;
    }

    .badge-success {
      background-color: #28a745;
      color: #fff;
    }

    .badge-secondary {
      background-color: #6c757d;
      color: #fff;
    }

    /* アイコン */
    .icon-check {
      color: #28a745;
    }

    .icon-times {
      color: #dc3545;
    }

    /* モノスペース */
    .mono {
      font-family: 'Courier New', Courier, monospace;
    }

    /* 印刷用スタイル */
    @media print {
      body {
        background-color: #fff;
      }

      .container {
        max-width: 100%;
        padding: 0;
      }

      .header, .table-list, .table-detail {
        box-shadow: none;
        border: 1px solid #ddd;
        margin-bottom: 20px;
      }

      .table-detail {
        page-break-inside: avoid;
      }

      .table-header {
        background-color: #f8f9fa;
      }
    }

    /* レスポンシブ */
    @media (max-width: 768px) {
      .container {
        padding: 10px;
      }

      .header {
        padding: 20px;
      }

      .header h1 {
        font-size: 1.8rem;
      }

      .table-grid {
        grid-template-columns: 1fr;
      }

      .data-table {
        font-size: 0.8rem;
      }

      .data-table th,
      .data-table td {
        padding: 8px 5px;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <!-- ヘッダー -->
  <div class="header{{ ($has_metadata ?? false) ? ' has-metadata' : '' }}">
    <h1>{{ $database }} - テーブル定義書</h1>
    <div class="meta-info">
      <p>生成日時: {{ $generated_at }}</p>
      <p>データベース: {{ $database }}</p>
      <p>テーブル数: {{ count($tables) }}</p>
      @if($has_metadata ?? false)
        <p style="color: #3498db;">📝 メタデータ適用済み</p>
      @endif
    </div>
  </div>

  <!-- テーブル一覧 -->
  <div class="table-list">
    <h2>テーブル一覧</h2>
    <div class="table-grid">
      @foreach($tables as $table)
        <div class="table-card">
          <a href="#table-{{ $table['name'] }}">
            <h3>{{ $table['name'] }}</h3>
            @if(isset($table['logical_name']) && $table['logical_name'])
              <p style="font-weight: bold; color: #2c3e50;">{{ $table['logical_name'] }}</p>
            @endif
            @if($table['description'] ?? $table['comment'])
              <p>{{ $table['description'] ?? $table['comment'] }}</p>
            @endif
          </a>
        </div>
      @endforeach
    </div>
  </div>

  <!-- 各テーブルの詳細 -->
  @foreach($tables as $table)
    <div id="table-{{ $table['name'] }}" class="table-detail">
      <div class="table-header">
        <h2>{{ $table['name'] }}{{ isset($table['logical_name']) && $table['logical_name'] ? ' - ' . $table['logical_name'] : '' }}</h2>
        @if($table['description'] ?? $table['comment'])
          <p>{{ $table['description'] ?? $table['comment'] }}</p>
        @endif
        <div class="table-meta">
          @if($table['engine'])
            <span>エンジン: {{ $table['engine'] }}</span>
          @endif
          @if($table['collation'])
            <span>照合順序: {{ $table['collation'] }}</span>
          @endif
          @if($table['primary_key'])
            <span>プライマリーキー: {{ implode(', ', $table['primary_key']) }}</span>
          @endif
        </div>
      </div>

      <!-- カラム情報 -->
      <div class="table-section">
        <h3>カラム情報</h3>
        <table class="data-table">
          <thead>
          <tr>
            <th>#</th>
            <th>カラム名</th>
            <th>論理名</th>
            <th>型</th>
            <th>NULL</th>
            <th>デフォルト</th>
            <th>その他</th>
            <th>説明</th>
          </tr>
          </thead>
          <tbody>
          @foreach($table['columns'] as $index => $column)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td>
                                <span class="mono">
                                    {{ $column['name'] }}
                                  @if(in_array($column['name'], $table['primary_key'] ?? []))
                                    <span class="badge badge-primary">PK</span>
                                  @endif
                                </span>
              </td>
              <td>{{ $column['logical_name'] ?? '-' }}</td>
              <td>
                <span class="mono">{{ $column['type'] }}</span>
                @if(!!$column['type_label'] && $column['type_label'] != $column['type'])
                  <div style="font-size: 0.85em; color: #666;">{{ $column['type_label'] }}</div>
                @endif
                @if($column['length'])
                  ({{ $column['length'] }})
                @elseif($column['precision'] && $column['scale'])
                  ({{ $column['precision'] }},{{ $column['scale'] }})
                @endif
                @if($column['unsigned'])
                  <small>UNSIGNED</small>
                @endif

                {{-- ENUM/SET値の表示 --}}
                @if($column['enum_values'] && count($column['enum_values']) > 0)
                  <div style="margin-top: 5px; font-size: 0.85em; color: #666;">
                    <strong>値:</strong>
                    @foreach($column['enum_values'] as $value)
                      @php
                        $label = $column['enum_labels'][$value] ?? $value;
                      @endphp
                      <span
                        style="background-color: #e9ecef; padding: 2px 6px; margin: 2px; border-radius: 3px; display: inline-block;">
                                            {{ $value }}{{ $label !== $value ? " ({$label})" : '' }}
                                        </span>
                    @endforeach
                  </div>
                @endif

                {{-- 制約情報の表示 --}}
                @if($column['constraints'] && count($column['constraints']) > 0)
                  <div style="margin-top: 5px; font-size: 0.85em; color: #666;">
                    @if(isset($column['constraints']['min_value']) || isset($column['constraints']['max_value']))
                      <div>
                        <strong>範囲:</strong>
                        @if(isset($column['constraints']['min_value']))
                          {{ $column['constraints']['min_value'] }}
                        @endif
                        〜
                        @if(isset($column['constraints']['max_value']))
                          {{ $column['constraints']['max_value'] }}
                        @endif
                      </div>
                    @endif

                    @if(isset($column['constraints']['max_length']))
                      <div>
                        <strong>最大長:</strong> {{ $column['constraints']['max_length'] }}文字
                      </div>
                    @endif

                    @if(isset($column['constraints']['check_constraints']) && count($column['constraints']['check_constraints']) > 0)
                      @foreach($column['constraints']['check_constraints'] as $check)
                        <div>
                          <strong>CHECK:</strong> {{ $check['clause'] }}
                        </div>
                      @endforeach
                    @endif
                  </div>
                @endif
              </td>
              <td>
                @if($column['nullable'])
                  <span class="icon-check">✓</span>
                @else
                  <span class="icon-times">✗</span>
                @endif
              </td>
              <td class="mono">{{ $column['default'] ?? '-' }}</td>
              <td>
                @if($column['auto_increment'])
                  <span class="badge badge-info">AI</span>
                @endif
                @if(isset($column['reference']))
                  <span class="badge badge-success">FK</span>
                  <div style="font-size: 0.8em; margin-top: 2px;">
                    → {{ $column['reference']['table'] }}.{{ $column['reference']['column'] }}
                    @if($column['reference']['label'] ?? false)
                      ({{ $column['reference']['label'] }})
                    @endif
                  </div>
                @endif
              </td>
              <td>{{ $column['description'] ?? $column['comment'] ?? '' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      <!-- インデックス情報 -->
      @if(count($table['indexes']) > 0)
        <div class="table-section">
          <h3>インデックス情報</h3>
          <table class="data-table">
            <thead>
            <tr>
              <th>インデックス名</th>
              <th>カラム</th>
              <th>種類</th>
            </tr>
            </thead>
            <tbody>
            @foreach($table['indexes'] as $index)
              <tr>
                <td class="mono">{{ $index['name'] }}</td>
                <td class="mono">{{ implode(', ', $index['columns']) }}</td>
                <td>
                  @if($index['is_primary'])
                    <span class="badge badge-primary">PRIMARY</span>
                  @elseif($index['is_unique'])
                    <span class="badge badge-success">UNIQUE</span>
                  @else
                    <span class="badge badge-secondary">INDEX</span>
                  @endif
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif

      <!-- 外部キー情報 -->
      @if(count($table['foreign_keys']) > 0)
        <div class="table-section">
          <h3>外部キー情報</h3>
          <table class="data-table">
            <thead>
            <tr>
              <th>制約名</th>
              <th>カラム</th>
              <th>参照テーブル</th>
              <th>参照カラム</th>
              <th>ON DELETE</th>
              <th>ON UPDATE</th>
            </tr>
            </thead>
            <tbody>
            @foreach($table['foreign_keys'] as $fk)
              <tr>
                <td class="mono">{{ $fk['name'] }}</td>
                <td class="mono">{{ implode(', ', $fk['columns']) }}</td>
                <td class="mono">{{ $fk['foreign_table'] }}</td>
                <td class="mono">{{ implode(', ', $fk['foreign_columns']) }}</td>
                <td>{{ $fk['on_delete'] ?? 'RESTRICT' }}</td>
                <td>{{ $fk['on_update'] ?? 'RESTRICT' }}</td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  @endforeach
</div>
</body>
</html>
