<?php

namespace Decorate\LaravelTableDocument;

use Illuminate\Support\ServiceProvider;
use Decorate\LaravelTableDocument\Commands\GenerateTableDocCommand;
use Decorate\LaravelTableDocument\Services\TableDocumentService;
use Decorate\LaravelTableDocument\Services\TableMetadataService;

class TableDocumentServiceProvider extends ServiceProvider
{
  /**
   * Register the application services.
   */
  public function register(): void
  {
    // 設定ファイルのマージ
    $this->mergeConfigFrom(
      __DIR__.'/../config/table-document.php', 'table-document'
    );

    // サービスの登録
    $this->app->singleton(TableMetadataService::class, function ($app) {
      return new TableMetadataService();
    });

    $this->app->singleton(TableDocumentService::class, function ($app) {
      return new TableDocumentService(
        $app->make(TableMetadataService::class)
      );
    });

    // Facadeのエイリアス
    $this->app->alias(TableDocumentService::class, 'table-document');
  }

  /**
   * Bootstrap the application services.
   */
  public function boot(): void
  {
    // ビューの登録
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'table-document');

    if ($this->app->runningInConsole()) {
      // コマンドの登録
      $this->commands([
        GenerateTableDocCommand::class,
      ]);

      // 設定ファイルの公開
      $this->publishes([
        __DIR__.'/../config/table-document.php' => config_path('table-document.php'),
      ], 'table-document-config');

      // ビューの公開
      $this->publishes([
        __DIR__.'/../resources/views' => resource_path('views/vendor/table-document'),
      ], 'table-document-views');
    }
  }

  /**
   * Get the services provided by the provider.
   *
   * @return array<string>
   */
  public function provides(): array
  {
    return [
      TableDocumentService::class,
      TableMetadataService::class,
      'table-document',
    ];
  }
}
