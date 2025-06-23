<?php

namespace Decorate\LaravelTableDocument\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getAllTablesInfo()
 * @method static array getTableInfo(string $tableName)
 * @method static array generateHtmlFile(bool $force = false)
 * @method static bool generateMetadataFile()
 * @method static ?array getGeneratedHtmlInfo()
 *
 * @see \Decorate\LaravelTableDocument\Services\TableDocumentService
 */
class TableDocument extends Facade
{
  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor(): string
  {
    return 'table-document';
  }
}
