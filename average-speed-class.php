<?php

/**
 * Class Average_Speed
 */
class Average_Speed {

	/**
	 * @var string
	 */
	private $item_separator = '--------------------------';

	/**
	 * @var null
	 */
	private $items = null;

	/**
	 * @var null
	 */
	private $file_path = null;

	/**
	 * @var null
	 */
	private $file_content = null;

	/**
	 * @var array
	 */
	private $by_hour    = [];

	/**
	 * @var array
	 */
	private $by_weekday = [];

	/**
	 * @var array
	 */
	private $servers    = [];

	/**
	 * Average_Speed constructor.
	 *
	 * @param $file_path
	 */
	public function __construct($file_path) {
		$this->file_path = $file_path;
		$this->read_file();
		$this->separate_items();
		$this->parse_items();
	}

	/**
	 *
	 */
	private function separate_items() {
		$items = explode($this->item_separator, $this->file_content);
		$items = array_filter($items, function($item) {
			return ! empty( trim($item) );
		});
		$this->items = $items;
	}

	/**
	 *
	 */
	private function read_file() {
		$this->file_content = file_get_contents($this->file_path);
	}

	/**
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
	 * @param $item
	 *
	 * @return float
	 */
	private function parse_download_speed($item) {
		preg_match('/Download: (.*) Mbps/', $item, $matches);
		return (float) ($matches[1]);
	}

	/**
	 * @param $item
	 *
	 * @return float
	 */
	private function parse_upload_speed($item) {
		preg_match('/Upload: (.*) Mbps/', $item, $matches);
		return (float) trim($matches[1]);
	}

	/**
	 * @param $item
	 *
	 * @return string
	 */
	private function parse_server($item) {
		preg_match('/Server: (.*) -/', $item, $matches);
		return trim($matches[1]);
	}

	/**
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
	 * @param $date
	 * @param $speed_array
	 */
	private function add_to_hour($date, $speed_array) {
		$hour = $date->format('H');
		if ( ! array_key_exists($hour, $this->by_hour) ) $this->by_hour[$hour] = [];
		$this->by_hour[$hour][] = $speed_array;
	}

	/**
	 * @param $date
	 * @param $speed_array
	 */
	private function add_to_weekday($date, $speed_array) {
		$weekday  = $date->format('N');
		if ( ! array_key_exists($weekday, $this->by_weekday) ) $this->by_weekday[$weekday] = [];
		$this->by_weekday[$weekday][] = $speed_array;
	}

	/**
	 * @param $server
	 */
	private function add_to_server_list($server) {
		if ( ! in_array($server, $this->servers) ) {
			$this->servers[] = $server;
		}
	}

	/**
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
	 *
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
	 * @param $items
	 *
	 * @return int
	 */
	public function count_items($items) {
		return count($items);
	}

	/**
	 * @return null
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * @return mixed
	 */
	public function first_record_date() {
		$items = $this->get_items();
		return  current($items)['datetime'];
	}

	/**
	 * @return mixed
	 */
	public function latest_record_date() {
		$items = $this->get_items();
		return  end($items)['datetime'];
	}

	/**
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
	 * @param $speed
	 *
	 * @return string
	 */
	private function format_speed($speed) {
		return number_format($speed, 3, '.', '');
	}

	/**
	 * @param $key
	 * @param $items
	 *
	 * @return array
	 */
	private function isolate_values_by_key($key, $items) {
		return array_map(function($item) use ($key) { return $item[$key . '_speed']; }, $items);
	}

	/**
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
	 * @return array
	 */
	public function get_items_by_hour() {
		return $this->parse_by_key('by_hour');
	}

	/**
	 * @return array
	 */
	public function get_items_by_weekday() {
		return $this->parse_by_key('by_weekday');
	}

}
