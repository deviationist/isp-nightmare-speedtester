#!/usr/bin/php
<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/average-speed-class.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$avgs = new Average_Speed(getenv('LOG_FILE_PATH'));

echo '===== Average speed measurement calculation =====' . PHP_EOL;

echo sprintf('First data recorded: %s', $avgs->first_record_date()) . PHP_EOL;
echo sprintf('Latest data recorded: %s', $avgs->latest_record_date()) . PHP_EOL;
$average_speed = $avgs->get_average_speed();
echo sprintf('Average speed (down / up): %s Mbps / %s Mbps', $average_speed->down, $average_speed->up) . PHP_EOL;

echo PHP_EOL . '===== Servers used =====' . PHP_EOL;
$table = new LucidFrame\Console\ConsoleTable();
$table->addHeader('Server name');
$items = $avgs->get_items();
foreach ( $avgs->get_servers() as $server ) {
	$table->addRow()
	      ->addColumn($server);
}
$table->display();

$table = $avgs->prepare_table('Hour');
$by_hour = $avgs->get_items_by_hour();
echo PHP_EOL . '===== Average speed grouped by hour =====' . PHP_EOL;
foreach ( $by_hour as $key => $value ) {
	$table->addRow()
      ->addColumn($key)
      ->addColumn($value->count)
      ->addColumn($value->average->down)
      ->addColumn($value->average->up)
      ->addColumn($value->min_max->down->min)
      ->addColumn($value->min_max->down->max)
	    ->addColumn($value->min_max->up->min)
	    ->addColumn($value->min_max->up->max);
}
$table->display();

$table = $avgs->prepare_table('Weekday');
$by_weekday = $avgs->get_items_by_weekday();
echo PHP_EOL . '===== Average speed grouped by weekday =====' . PHP_EOL;
foreach ( $by_weekday as $key => $value ) {
	$table->addRow()
	      ->addColumn(jddayofweek($key-1, 1))
	      ->addColumn($value->count)
	      ->addColumn($value->average->down)
	      ->addColumn($value->average->up)
	      ->addColumn($value->min_max->down->min)
	      ->addColumn($value->min_max->down->max)
	      ->addColumn($value->min_max->up->min)
	      ->addColumn($value->min_max->up->max);
}
$table->display();

$table = new LucidFrame\Console\ConsoleTable();
$table->addHeader('Datetime')
      ->addHeader('Speed down (Mbps)')
      ->addHeader('Speed up (Mbps)')
      ->addHeader('Server');
$items = $avgs->get_items();
echo PHP_EOL . '===== All measurements =====' . PHP_EOL;
foreach ( $items as $item ) {
	$table->addRow()
	      ->addColumn($item['datetime'])
	      ->addColumn($item['download_speed'])
	      ->addColumn($item['upload_speed'])
	      ->addColumn($item['server']);
}
$table->display();
