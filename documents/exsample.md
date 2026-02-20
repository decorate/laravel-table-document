# Migrationファイルの書き方ガイド

このパッケージはデータベースの情報を直接読み取って定義書を生成します。
Migrationファイルを適切に書くことで、より充実した定義書を自動生成できます。

---

## テーブルコメント・カラムコメント

テーブルコメントとカラムコメントはそのまま定義書に反映されます。

### コメントの書式

`|` 区切りで **論理名** と **説明** を同時に設定できます。

```
論理名|説明文
```

`|` がない場合は全体が論理名として扱われます。

---

## Migrationの基本例

### ユーザーテーブル

```php
Schema::create('users', function (Blueprint $table) {
    $table->comment('ユーザー|システムのユーザー情報を管理するテーブル');

    $table->id()->comment('ID');
    $table->string('name', 100)->comment('氏名|ユーザーのフルネーム');
    $table->string('email', 255)->unique()->comment('メールアドレス|ログインに使用するメールアドレス。重複不可');
    $table->string('password')->comment('パスワード|ハッシュ化されたパスワード');
    $table->boolean('is_active')->default(true)->comment('有効フラグ|0:無効 1:有効');
    $table->timestamps();
    $table->softDeletes();
});
```

### 商品テーブル（ENUM・DECIMAL・インデックスあり）

```php
Schema::create('products', function (Blueprint $table) {
    $table->comment('商品|販売商品のマスターテーブル');

    $table->id()->comment('商品ID');
    $table->string('code', 20)->comment('商品コード|SKUなど一意の商品識別コード');
    $table->string('name', 200)->comment('商品名');
    $table->text('description')->nullable()->comment('説明|商品の詳細説明');
    $table->decimal('price', 10, 2)->unsigned()->comment('価格|税抜き販売価格（円）');
    $table->unsignedInteger('stock')->default(0)->comment('在庫数');
    $table->enum('status', ['draft', 'published', 'discontinued'])
        ->default('draft')
        ->comment('ステータス|draft:下書き published:公開中 discontinued:廃番');
    $table->timestamps();

    $table->unique('code');
    $table->index('status');
});
```

### 注文テーブル（外部キーあり）

```php
Schema::create('orders', function (Blueprint $table) {
    $table->comment('注文|顧客の注文情報');

    $table->id()->comment('注文ID');
    $table->foreignId('user_id')
        ->constrained('users')
        ->onDelete('restrict')
        ->onUpdate('cascade')
        ->comment('ユーザーID|注文したユーザーのID');
    $table->string('order_no', 20)->comment('注文番号|表示用の注文番号（例: ORD-20240101-001）');
    $table->enum('status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])
        ->default('pending')
        ->comment('注文ステータス');
    $table->decimal('total_amount', 12, 2)->comment('合計金額|税込み合計金額（円）');
    $table->text('note')->nullable()->comment('備考');
    $table->timestamp('ordered_at')->comment('注文日時');
    $table->timestamps();

    $table->unique('order_no');
    $table->index(['user_id', 'status']);
    $table->index('ordered_at');
});
```

### 注文明細テーブル（複合主キー）

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->comment('注文明細|注文に含まれる商品の明細');

    $table->id()->comment('明細ID');
    $table->foreignId('order_id')
        ->constrained('orders')
        ->onDelete('cascade')
        ->comment('注文ID');
    $table->foreignId('product_id')
        ->constrained('products')
        ->onDelete('restrict')
        ->comment('商品ID');
    $table->unsignedInteger('quantity')->comment('数量');
    $table->decimal('unit_price', 10, 2)->comment('単価|注文時点の税抜き単価（円）');
    $table->decimal('subtotal', 12, 2)->comment('小計|単価 × 数量（税抜き）');
    $table->timestamps();

    $table->index('order_id');
    $table->index('product_id');
});
```

---

## カラム型ごとのポイント

### 数値型

```php
// 整数（unsignedにすると最小値0・最大値が定義書に表示される）
$table->unsignedTinyInteger('sort_order')->default(0)->comment('表示順');
$table->unsignedSmallInteger('age')->nullable()->comment('年齢');
$table->unsignedInteger('view_count')->default(0)->comment('閲覧数');
$table->unsignedBigInteger('total_sales')->default(0)->comment('累計売上数');

// 小数（precision, scaleが定義書に表示される）
$table->decimal('rate', 5, 2)->comment('割引率|0.00〜100.00（%）');
$table->float('latitude')->nullable()->comment('緯度');
```

### 文字列型

```php
// 最大文字数が定義書に表示される
$table->char('pref_code', 2)->comment('都道府県コード|JIS X 0401準拠の2桁コード');
$table->string('phone', 20)->nullable()->comment('電話番号|ハイフンあり形式（例: 03-1234-5678）');
$table->string('postal_code', 8)->nullable()->comment('郵便番号|ハイフンあり形式（例: 123-4567）');
$table->text('body')->nullable()->comment('本文');
$table->mediumText('content')->nullable()->comment('コンテンツ');
$table->longText('raw_data')->nullable()->comment('生データ');
```

### 日付・時刻型

```php
$table->date('birth_date')->nullable()->comment('生年月日');
$table->time('open_time')->nullable()->comment('営業開始時刻');
$table->datetime('published_at')->nullable()->comment('公開日時');
$table->timestamp('expires_at')->nullable()->comment('有効期限');
```

### JSON型

```php
$table->json('settings')->nullable()->comment('設定|JSON形式で格納する各種設定値');
$table->json('meta')->nullable()->comment('メタ情報');
```

---

## メタデータYAMLとの併用

Migrationのコメントだけでは表現しきれない情報は、メタデータYAMLで補完できます。

### メタデータYAML生成

```bash
php artisan table:doc --generate-metadata
```

### YAMLの記述例（config/table-metadata.yaml）

```yaml
tables:
  products:
    logical_name: 商品
    description: 販売商品のマスターテーブル
    columns:
      status:
        logical_name: ステータス
        description: 商品の公開状態を管理する
        enum_labels:
          draft: 下書き
          published: 公開中
          discontinued: 廃番

  users:
    logical_name: ユーザー
    description: システムのユーザー情報を管理するテーブル
    columns:
      is_active:
        logical_name: 有効フラグ
        boolean_labels:
          true: 有効
          false: 無効

settings:
  # 全テーブル共通のカラム定義（各テーブルで個別定義すると上書き可能）
  common_columns:
    created_at:
      logical_name: 作成日時
      description: レコードが作成された日時
    updated_at:
      logical_name: 更新日時
      description: レコードが最後に更新された日時
    deleted_at:
      logical_name: 削除日時
      description: 論理削除された日時（NULLの場合は未削除）
```

---

## 定義書の生成コマンド

```bash
# 静的HTMLを生成（初回）
php artisan table:doc --format=static-html

# 強制再生成
php artisan table:doc --format=static-html --force

# メタデータを生成/更新してからHTML生成
php artisan table:doc --update-metadata
php artisan table:doc --format=static-html --force

# データベースとメタデータの差分確認
php artisan table:doc --check-diff
```