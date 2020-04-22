<?php

/**
 * Class Average_Speed
 */
class Average_Speed {

	/**
	 * The string used to separate log file items.
	 *
	 * @var string
	 */
	private $item_separator = '--------------------------';

	/**
	 * Property to store log file items.
	 *
	 * @var null
	 */
	private $items = null;

	/**
	 * Log file path.
	 *
	 * @var null
	 */
	private $file_path = null;

	/**
	 * Log file content.
	 *
	 * @var null
	 */
	private $file_content = null;

	/**
	 * By hour statistics property.
	 *
	 * @var array
	 */
	private $by_hour = [];

	/**
	 * By weekday statistics property.
	 *
	 * @var array
	 */
	private $by_weekday = [];

	/**
	 * Server list.
	 *
	 * @var array
	 */
	private $servers = [];

	/**
	 * Average_Speed constructor.
	 *
	 * @param $file_path
	 */
	public function __construct($file_path) {
		$this->file_path = $file_path;
		if ( ! $this->read_file() ) {
			return false;
		}
		$this->separate_items();
		$this->parse_items();
		return true;
	}

	/**
	 * Separate the log file items into array.
	 */
	private function separate_items() {
		$items = explode($this->item_separator, $this->file_content);
		$items = array_filter($items, function($item) {
			return ! empty( trim($item) );
		});
		$this->items = $items;
	}

	/**
	 * Read the log file.
	 */
	private function read_file() {
		if ( ! file_exists($this->file_path) || ! is_readable($this->file_path) ) return false;
		$this->file_content = file_get_contents($this->file_path);
		return true;
	}

	/**
	 * Parse the datetime from log file item.
	 *
	 * @param $item
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	private function parse_datetime($item) {
		preg_match('/Speed test start: (.+)/', $item, $matches);
		$datetime_raw = $matches[1];
		$timestamp = strtotime($datetime_raw);
		$date = new DateTime();
		$date->setTimestamp($timestamp);
		$date->setTimezone(new DateTimeZone('Europe/Oslo'));
		return $date;
	}

	/**
	 * Parse download speed from log item.
	 *
	 * @param $item
	 *
	 * @return float
	 */
	private function parse_download_speed($item) {
		preg_match('/Download: (.*) Mbps/', $item, $matches);
		return isset($matches[1]) ? (float) $matches[1] : 0;
	}

	/**
	 * Parse upload speed from log item.
	 *
	 * @param $item
	 *
	 * @return float
	 */
	private function parse_upload_speed($item) {
		preg_match('/Upload: (.*) Mbps/', $item, $matches);
		return isset($matches[1]) ? (float) $matches[1] : 0;
	}

	/**
	 * Parse server from log item.
	 *
	 * @param $item
	 *
	 * @return string
	 */
	private function parse_server($item) {
		preg_match('/Server: (.*) -/', $item, $matches);
		return isset($matches[1]) ? trim($matches[1]) : false;
	}

	/**
	 * Parse speed from log item.
	 *
	 * @param $item
	 *
	 * @return array
	 */
	private function parse_speed($item) {
		return [
			'download_speed' => $this->parse_download_speed($item),
			'upload_speed'   => $this->parse_upload_speed($item),
		];
	}

	/**
	 * Add item statistics to hour statistics.
	 *
	 * @param $date
	 * @param $speed_array
	 */
	private function add_to_hour($date, $speed_array) {
		$hour = $date->format('H');
		if ( ! array_key_exists($hour, $this->by_hour) ) $this->by_hour[$hour] = [];
		$this->by_hour[$hour][] = $speed_array;
	}

	/**
	 * Add item statistics to weekday statistics.
	 *
	 * @param $date
	 * @param $speed_array
	 */
	private function add_to_weekday($date, $speed_array) {
		$weekday  = $date->format('N');
		if ( ! array_key_exists($weekday, $this->by_weekday) ) $this->by_weekday[$weekday] = [];
		$this->by_weekday[$weekday][] = $speed_array;
	}

	/**
	 * Add server to server list.
	 *
	 * @param $server
	 */
	private function add_to_server_list($server) {
		if ( $server && ! in_array($server, $this->servers) ) {
			$this->servers[] = $server;
		}
	}

	/**
	 * Prepare results table.
	 *
	 * @param $first_column
	 *
	 * @return \LucidFrame\Console\ConsoleTable
	 */
	public function prepare_table($first_column) {
		$table = new LucidFrame\Console\ConsoleTable();
		$table->addHeader($first_column)
		      ->addHeader('Number of measurements')
		      ->addHeader('Average speed down')
		      ->addHeader('Average speed up')
		      ->addHeader('Speed down min')
		      ->addHeader('Speed down max')
		      ->addHeader('Speed up min')
		      ->addHeader('Speed up max');
		return $table;
	}

	/**
	 * Parse log file items.
	 */
	private function parse_items() {

		$this->items = array_map(function($item) {

			$date        = $this->parse_datetime($item);
			$datetime    = $date->format('Y-m-d H:i:s (e)');
			$speed_array = $this->parse_speed($item);
			$server      = $this->parse_server($item);

			return array_merge($speed_array, compact('speed_array', 'datetime', 'date', 'server'));

		}, $this->items);

		$this->items = array_filter($this->items, function($item) {
			return ( $item['download_speed'] > 0 && $item['upload_speed'] > 0);
		});
		$this->items = array_values($this->items);

		$this->items = array_map(function($item) {
			$this->add_to_server_list($item['server']);
			$this->add_to_hour($item['date'], $item['speed_array']);
			$this->add_to_weekday($item['date'], $item['speed_array']);
			unset($item['speed_array']);
			return $item;
		}, $this->items);

	}

	/**
	 * Count items.
	 *
	 * @param $items
	 *
	 * @return int
	 */
	public function count_items($items) {
		return count($items);
	}

	/**
	 * Get items.
	 *
	 * @return null
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Get the first log item.
	 *
	 * @return mixed
	 */
	public function first_record_date() {
		$items = $this->get_items();
		return  current($items)['datetime'];
	}

	/**
	 * Get the latest log item.
	 *
	 * @return mixed
	 */
	public function latest_record_date() {
		$items = $this->get_items();
		return  end($items)['datetime'];
	}

	/**
	 * Get all servers included in the log file.
	 *
	 * @param bool $as_string
	 * @param string $separator
	 *
	 * @return array|string
	 */
	public function get_servers($as_string = false, $separator = ', ') {
		if ( $as_string ) {
			return implode($this->servers, $separator);
		}
		return $this->servers;
	}

	/**
	 * Format the speed value.
	 *
	 * @param $speed
	 *
	 * @return string
	 */
	private function format_speed($speed) {
		return number_format($speed, 3, '.', '');
	}

	/**
	 * Flatten array based on a specific key.
	 *
	 * @param $key
	 * @param $items
	 *
	 * @return array
	 */
	private function isolate_values_by_key($key, $items) {
		return array_map(function($item) use ($key) { return $item[$key . '_speed']; }, $items);
	}

	/**
	 * Calculate average speed.
	 *
	 * @param $key
	 * @param $items
	 *
	 * @return string
	 */
	private function calculate_average_speed($key, $items) {
		$sum = array_sum( $this->isolate_values_by_key($key, $items) );
		return $this->format_speed($sum / $this->count_items($items));
	}

	/**
	 * Get average up/down speed.
	 * @param array $items
	 *
	 * @return object
	 */
	public function get_average_speed($items = []) {
		if ( empty($items) ) {
			$items = $this->get_items();
		}
		return (object) [
			'down' => $this->calculate_average_speed('download', $items),
			'up'   => $this->calculate_average_speed('upload', $items),
		];
	}

	/**
	 * Get minimum and maximum speed value.
	 *
	 * @param $key
	 * @param $items
	 *
	 * @return object
	 */
	public function get_min_max_speed($key, $items) {
		$values = $this->isolate_values_by_key($key, $items);
		return (object) [
			'min' => min($values),
			'max' => max($values),
		];
	}

	/**
	 * Parse speed data by key.
	 *
	 * @param $key
	 *
	 * @return array
	 */
	private function parse_by_key($key) {
		$items = [];
		foreach ( $this->{$key} as $key => $value ) {
			$min_max = (object) [
				'down' => $this->get_min_max_speed('download', $value),
				'up'   => $this->get_min_max_speed('upload', $value),
			];
			$count = count($value);
			$average = $this->get_average_speed($value);
			$items[$key] = (object) compact('min_max', 'count', 'average');
		}
		ksort($items);
		return $items;
	}

	/**
	 * Get items by hour.
	 *
	 * @return array
	 */
	public function get_items_by_hour() {
		return $this->parse_by_key('by_hour');
	}

	/**
	 * Get items by weekday.
	 *
	 * @return array
	 */
	public function get_items_by_weekday() {
		return $this->parse_by_key('by_weekday');
	}

}
