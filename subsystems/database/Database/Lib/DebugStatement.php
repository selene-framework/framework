<?php

namespace Electro\Database\Lib;

use PDO;
use PDOException;
use PhpKit\ExtPDO\ExtPDO;
use PhpKit\WebConsole\DebugConsole\DebugConsole;

/**
 * Note: this class implements a decorator pattern but it still extends `PDOStatement` to be compatible with
 * functions that receive a type-hinted `PDOStatement` argument.
 *
 * <p>Note: rows are counted as they are fetched from the statement object; the total count is displayed either when
 * a new query is executed or by an external call to `endLog` when the application logic has finished executing.
 *
 * <p>Warning: when two or more statements are being read simultaneously, the row count will not be correct.
 */
class DebugStatement extends \PDOStatement
{
  /** @var string The SQL command, always in lower case (ex: 'select') */
  protected $command;
  /** @var \PDOStatement */
  protected $decorated;
  /** @var bool */
  protected $isSelect;
  /** @var array */
  protected $params = [];
  /** @var string The full SQL query */
  protected $query;
  /** @var int Number of rows fetched from a SELECT, -1 if no fetch function called (must have been an INSERT/UPDATE/DELETE statement)*/
  protected $fetchedCount = -1;
  /** @var ExtPDO */
  private $pdo;

  public function __construct (\PDOStatement $statement, $query, $pdo)
  {
    $this->decorated = $statement;
    $this->query     = trim ($query);
    $this->command   = strtolower (str_extractSegment ($this->query, '/\s/')[0]);
    $this->isSelect  = $this->command == 'select';
    $this->pdo       = $pdo;
  }

  function __debugInfo ()
  {
    return [
      'decorated'  => $this->decorated,
      'query'      => $this->query,
      'params'     => $this->params,
      'fetchCount' => $this->rowCount (),
    ];
  }

  public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null): bool
	{
    return $this->decorated->bindColumn ($column, $param, $type, $maxlen, $driverdata);
  }

  public function bindParam ($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = null,
                             $driver_options = null): bool
	{
    return $this->decorated->bindParam ($parameter, $variable, $data_type, $length, $driver_options);
  }

  public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR): bool
	{
    $this->params[is_numeric ($parameter) ? $parameter - 1 : $parameter] = $value;
    return $this->decorated->bindValue ($parameter, $value, $data_type);
  }

  public function closeCursor(): bool
	{
    $this->params = [];
    return $this->decorated->closeCursor ();
  }

  public function columnCount(): int
	{
    return $this->decorated->columnCount ();
  }

  public function debugDumpParams(): bool
	{
    return $this->decorated->debugDumpParams ();
  }

  public function errorCode(): string
	{
    return $this->decorated->errorCode ();
  }

  public function errorInfo(): array
	{
    return $this->decorated->errorInfo ();
  }

  public function execute($params = null): bool
	{
    if (isset($params))
      $this->params = $params;
    $this->logQuery ();
    $r = $this->profile (function () use ($params) {
      return $this->decorated->execute ($params);
    });
    $this->endLog ();
    return $r;
  }

  public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0): mixed
	{
	  $this->fetchedCount++;
		return $this->decorated->fetch ($fetch_style, $cursor_orientation, $cursor_offset);
  }

  public function fetchAll($fetch_style = null, $fetch_argument = null, mixed ...$ctor_args): array
	{
	  $return = null;
		$count = func_num_args ();
    switch ($count) {
      case 0:
        $return = $this->decorated->fetchAll ();
        break;
      case 1:
        $return = $this->decorated->fetchAll ($fetch_style);
        break;
      case 2:
        $return = $this->decorated->fetchAll ($fetch_style, $fetch_argument);
        break;
      default:
        $return = $this->decorated->fetchAll ($fetch_style, $fetch_argument, $ctor_args);
    }
	$this->fetchedCount = count($return);
	return $return;
  }

  public function fetchColumn($column_number = 0): mixed
	{
	  $this->fetchedCount++;
		return $this->decorated->fetchColumn ($column_number);
  }

  public function fetchObject($class_name = "stdClass", $ctor_args = null): object|false
	{
	  $this->fetchedCount++;
		return $this->decorated->fetchObject ($class_name, $ctor_args);
  }

  public function getAttribute($attribute): mixed
	{
    return $this->decorated->getAttribute ($attribute);
  }

  public function getColumnMeta($column): array|false
	{
    return $this->decorated->getColumnMeta ($column);
  }

  public function nextRowset(): bool
	{
    return $this->decorated->nextRowset ();
  }

  public function rowCount(): int
	{
	  if ($this->fetchedCount < 0)
			return $this->decorated->rowCount ();
	  else
		  return $this->fetchedCount;
  }

  public function setAttribute($attribute, $value): bool
	{
    return $this->decorated->setAttribute ($attribute, $value);
  }

  public function setFetchMode($mode, mixed ...$params)
	{
    return $this->decorated->setFetchMode ($mode);
  }

  protected function endLog ()
  {
    $log   = DebugConsole::logger ('database');
    $count = $this->rowCount ();
    if (is_null ($count)) {
      $log->write ('; unknown result set size');
    }
    else
      $log->write (sprintf ('; <b>%d</b> %s %s',
        $count,
        $count == 1 ? 'record' : 'records',
        $this->isSelect ? 'returned' : 'affected'
      ));
    $log->write ('</#footer></#section>');
  }

  protected function logDuration ($dur)
  {
    DebugConsole::logger ('database')
                ->write (sprintf ('<#footer>Query took <b>%s</b> milliseconds', $dur * 1000));
  }

  protected function logQuery ()
  {
    DebugConsole::logger ('database')
                ->inspect ("<#section|SQL " . ($this->isSelect ? 'QUERY' : 'STATEMENT') . ">",
                  SqlFormatter::highlightQuery ($this->query));
    if ($this->params)
      DebugConsole::logger ('database')->write ("<#header>Parameters</#header>")->inspect ($this->params);
  }

  protected function profile (callable $action)
  {
    $start = microtime (true);
    try {
      $r = $action ();
    }
    catch (PDOException $e) {
      DebugConsole::logger ('database')->write ('<#footer><#alert>Query failed!</#alert></#footer>');
      DebugConsole::throwErrorWithLog ($e);
    }
    $end = microtime (true);
    $dur = round ($end - $start, 4);
    $this->logDuration ($dur);
    /** @noinspection PhpUndefinedVariableInspection */
    return $r;
  }
}
