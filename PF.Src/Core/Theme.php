<?php

namespace Core;

class Theme extends Model {
	private static $_active;

	public function __construct($flavorId = null) {
		parent::__construct();

		if ($flavorId !== null) {
			self::$_active = $this->db->select('t.*, ts.folder AS flavor_folder')
				->from(':theme_style', 'ts')
				->join(':theme', 't', ['t.theme_id' => ['=' => 'ts.theme_id']])
				->where(['ts.style_id' => (int) $flavorId])
				->get();

			if (!self::$_active) {
				throw new \Exception('Not a valid flavor.');
			}
		}

		if (!self::$_active) {
			$cookie = \Phpfox::getCookie('flavor_id');

			if ($cookie) {
				self::$_active = $this->db->select('t.*, ts.folder AS flavor_folder')
					->from(':theme_style', 'ts')
					->join(':theme', 't', ['t.theme_id' => ['=' => 'ts.theme_id']])
					->where(['ts.style_id' => (int) $cookie])
					->get();
			}
			else {
				self::$_active = $this->db->select('t.*, ts.folder AS flavor_folder')
					->from(':theme', 't')
					->join(':theme_style', 'ts', ['t.theme_id' => ['=' => 'ts.theme_id'], 'ts.is_default' => 1])
					->where(($cookie ? ['t.theme_id' => (int) $cookie] : ['t.is_default' => 1]))
					->get();
			}

			if (!self::$_active || defined('PHPFOX_CSS_FORCE_DEFAULT')) {
				self::$_active = [
					'name' => 'Default',
					'folder' => 'default',
					'flavor_folder' => 'default'
				];
			}
		}
	}

	public function import($zip = null, $extra = null) {
		$file = PHPFOX_DIR_FILE . 'static/' . uniqid() . '/';
		mkdir($file);

		if ($zip === null) {
			$zip = $file . 'import.zip';
			file_put_contents($zip, file_get_contents('php://input'));
		}

		$Zip = new \ZipArchive();
		$Zip->open($zip);
		$Zip->extractTo($file);
		$Zip->close();

		$themeId = null;
		$File = \Phpfox_File::instance();
		foreach (scandir($file) as $f) {
			if ($File->extension($f) == 'json') {
				$data = json_decode(file_get_contents($file . $f));

				$themeId = $this->make([
					'name' => $data->name,
					'extra' => ($extra ? json_encode($extra) : null)
				], $data->files);

				$File->delete_directory($file);
				$iteration = 0;
				foreach ($data->flavors as $flavorId => $flavorName) {
					$iteration++;

					$this->db->insert(':theme_style', [
						'theme_id' => $themeId,
						'name' => $flavorName,
						'folder' => $flavorId,
						'is_default' => ($iteration === 1 ? '1' : '0')
					]);
				}
			}
		}

		if ($themeId === null) {
			throw new \Exception('Theme is missing its JSON file.');
		}

		return $themeId;
	}

	/**
	 * @param $val
	 * @param null $files
	 * @return Theme\Object
	 */
	public function make($val, $files = null) {
		/*
		$check = $this->db->select('COUNT(*) AS total')
			->from(':theme')
			->where(['folder' => $val['folder']])
			->get();

		if ($check['total']) {
			throw error('Folder already exists.');
		}
		*/
		$id = $this->db->insert(':theme', [
			'name' => $val['name'],
			// 'folder' => $val['folder'],
			'website' => (isset($val['extra']) ? $val['extra'] : null),
			'created' => PHPFOX_TIME,
			'is_active' => 1
		]);
		$this->db->update(':theme', ['folder' => $id], ['theme_id' => $id]);

		if ($files !== null) {
			foreach ($files as $name => $content) {
				$path = PHPFOX_DIR_SITE . 'themes/' . $id . '/' . $name;

				$parts = pathinfo($path);
				if (!is_dir($parts['dirname'])) {
					mkdir($parts['dirname'], 0777, true);
				}

				file_put_contents($path, $content);
			}

			return $id;
		}

		$flavorId = $this->db->insert(':theme_style', [
			'theme_id' => $id,
			'is_active' => 1,
			'is_default' => 1,
			'name' => 'Default',
			// 'folder' => 'default'
		]);
		$this->db->update(':theme_style', ['folder' => $flavorId], ['style_id' => $flavorId]);

		$File = \Phpfox_File::instance();
		$copy = [];
		$dirs = [];
		$files = $File->getAllFiles(PHPFOX_DIR. 'theme/default/');
		foreach ($files as $file) {
			if (!in_array($File->extension($file), [
				'html', 'js', 'css', 'less'
			])) {
				continue;
			}

			$parts = pathinfo($file);

			$dirs[] = str_replace(PHPFOX_DIR . 'theme/default/', '', $parts['dirname']);
			$copy[] = $file;
		}

		$path = PHPFOX_DIR_SITE . 'themes/' . $id . '/';
		foreach ($dirs as $dir) {
			if (!is_dir($path . $dir)) {
				mkdir($path . $dir, 0777, true);
			}
		}

		foreach ($copy as $file) {
			$newFile = $path . str_replace(PHPFOX_DIR . 'theme/default/', '', $file);
			if (in_array($File->extension($file), ['less', 'css'])) {
				$newFile = str_replace('default.' . $File->extension($file), $flavorId . '.' . $File->extension($file), $newFile);
			}

			copy($file, $newFile);
			if ($File->extension($file) == 'less') {
				$content = file_get_contents($newFile);
				$content = str_replace('../../../', '../../../../PF.Base/', $content);
				file_put_contents($newFile, $content);
			}
		}

		return $this->get($id);
	}

	/**
	 * @return Theme\Object
	 */
	public function get($id = null) {
		$data = self::$_active;
		if ($id === null && !$data) {
			$data = $this->db->select('t.*, ts.style_id AS flavor_id, ts.folder AS flavor_folder')
				->from(':theme', 't')
				->join(':theme_style', 'ts', ['t.theme_id' => ['=' => 'ts.theme_id']])
				->where(['t.is_default' => 1])
				->get();
		}

		if ($id !== null) {
			$data = $this->db->select('t.*, ts.style_id AS flavor_id, ts.folder AS flavor_folder')
				->from(':theme', 't')
				->join(':theme_style', 'ts', ['t.theme_id' => ['=' => 'ts.theme_id']])
				->where(['t.theme_id' => (int) $id])
				->get();
		}

		if (!$data) {
			throw error('Theme not found.');
		}

		return new Theme\Object($data);
	}

	/**
	 * @return Theme\Object[]
	 */
	public function all() {
		$rows = $this->db->select('t.*')
			->from(':theme', 't')
			->order('t.name ASC')
			->all();

		$themes = [];
		foreach ($rows as $row) {
			$Theme = new Theme\Object($row);

			if ($Theme->folder == 'default') {
				continue;
			}

			if (!is_dir($Theme->getPath())) {
				continue;
			}

			$themes[] = $Theme;
		}

		return $themes;
	}
}