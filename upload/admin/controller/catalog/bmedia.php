<?php
/**
 * Quick Product Manager (bproduct) - image popover backend: upload, download from URL, size limits.
 * Files are stored in image/catalog and validated with the same store settings as the default file manager.
 */
class ControllerCatalogBmedia extends Controller {
	private $downloaded_tmp = '';

	private function allowed() {
		return $this->user->hasPermission('modify', 'catalog/product') || $this->user->hasPermission('modify', 'catalog/category');
	}

	private function json($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function directory() {
		$base = rtrim(DIR_IMAGE . 'catalog', '/');
		$raw = isset($this->request->get['directory']) ? $this->request->get['directory'] : '';

		if ($raw === '') {
			return $base;
		}

		$path = realpath($base . '/' . str_replace(array('*', '..'), '', $raw));

		return ($path && is_dir($path) && strpos($path, realpath($base)) === 0) ? $path : $base;
	}

	private function formatSize($bytes) {
		$units = array('B', 'KB', 'MB', 'GB');
		$i = 0;

		while ($bytes >= 1024 && $i < 3) {
			$bytes /= 1024;
			$i++;
		}

		return round($bytes) . ' ' . $units[$i];
	}

	// Lists the folders and images of image/catalog for the multi-select media window
	public function browse() {
		$this->load->language('common/filemanager');

		if (!$this->allowed()) {
			$this->json(array('error' => $this->language->get('error_permission')));

			return;
		}

		$base = realpath(DIR_IMAGE . 'catalog');
		$rel = isset($this->request->get['directory']) ? trim(str_replace(array('*', '..', '\\'), '', $this->request->get['directory']), '/') : '';
		$dir = $rel === '' ? $base : realpath($base . '/' . $rel);

		if (!$dir || !is_dir($dir) || strpos($dir, $base) !== 0) {
			$dir = $base;
			$rel = '';
		} else {
			$rel = ltrim(substr($dir, strlen($base)), '/');
		}

		$q = isset($this->request->get['q']) ? utf8_strtolower(trim($this->request->get['q'])) : '';
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 48;

		$folders = array();
		$files = array();

		foreach ((array)scandir($dir) as $name) {
			if ($name === '' || $name[0] === '.') {
				continue;
			}

			if ($q !== '' && strpos(utf8_strtolower($name), $q) === false) {
				continue;
			}

			$path = $dir . '/' . $name;

			if (is_dir($path)) {
				$folders[] = array('name' => $name, 'path' => ltrim($rel . '/' . $name, '/'));
			} elseif (is_file($path) && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
				$files[] = array('name' => $name, 'time' => filemtime($path));
			}
		}

		usort($files, function ($a, $b) {
			return $b['time'] - $a['time'];
		});

		$total = count($files);

		$this->load->model('tool/image');

		$images = array();

		foreach (array_slice($files, ($page - 1) * $limit, $limit) as $file) {
			$path = 'catalog/' . ltrim($rel . '/' . $file['name'], '/');

			$images[] = array('name' => $file['name'], 'path' => $path, 'thumb' => (string)$this->model_tool_image->resize($path, 120, 120));
		}

		$parent = null;

		if ($rel !== '') {
			$parent = dirname($rel) === '.' ? '' : dirname($rel);
		}

		$this->json(array(
			'directory' => $rel,
			'parent'    => $parent,
			'folders'   => $page == 1 ? $folders : array(),
			'images'    => $images,
			'page'      => $page,
			'pages'     => max(1, (int)ceil($total / $limit)),
			'total'     => $total
		));
	}

	public function limits() {
		$max = (int)$this->config->get('config_file_max_size');

		$this->json(array('max_size' => $max, 'max_text' => $this->formatSize($max)));
	}

	public function upload() {
		$this->load->language('common/filemanager');
		$this->load->language('catalog/bmedia');

		$json = array();

		if (!$this->allowed()) {
			$json['error'] = $this->language->get('error_permission');
		}

		$directory = $this->directory();

		if (!$json && !is_dir($directory)) {
			$json['error'] = $this->language->get('error_directory');
		}

		$uploaded = array();

		if (!$json) {
			$files = array();

			if (!empty($this->request->files['file']['name']) && is_array($this->request->files['file']['name'])) {
				foreach (array_keys($this->request->files['file']['name']) as $key) {
					$files[] = array(
						'name'     => $this->request->files['file']['name'][$key],
						'type'     => $this->request->files['file']['type'][$key],
						'tmp_name' => $this->request->files['file']['tmp_name'][$key],
						'error'    => $this->request->files['file']['error'][$key],
						'size'     => $this->request->files['file']['size'][$key]
					);
				}
			}

			foreach ($files as $file) {
				if (!is_file($file['tmp_name'])) {
					$json['error'] = $this->language->get('error_upload');

					break;
				}

				$filename = basename(html_entity_decode($file['name'], ENT_QUOTES, 'UTF-8'));
				$filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);

				if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 255)) {
					$json['error'] = $this->language->get('error_filename');

					break;
				}

				$allowed = array();

				foreach (explode("\n", str_replace("\r", '', (string)$this->config->get('config_file_ext_allowed'))) as $ext) {
					if (trim($ext) !== '') {
						$allowed[] = strtolower(trim($ext));
					}
				}

				if (!in_array(strtolower(substr(strrchr($filename, '.'), 1)), $allowed)) {
					$json['error'] = $this->language->get('error_filetype');

					break;
				}

				$mimes = array();

				foreach (explode("\n", str_replace("\r", '', (string)$this->config->get('config_file_mime_allowed'))) as $mime) {
					if (trim($mime) !== '') {
						$mimes[] = trim($mime);
					}
				}

				$finfo = new finfo(FILEINFO_MIME_TYPE);

				if (!in_array($finfo->file($file['tmp_name']), $mimes) || !@getimagesize($file['tmp_name'])) {
					$json['error'] = $this->language->get('error_filetype');

					break;
				}

				if ($file['size'] > (int)$this->config->get('config_file_max_size')) {
					$json['error'] = $this->language->get('error_filesize');

					break;
				}

				if ($file['error'] != UPLOAD_ERR_OK) {
					$json['error'] = $this->language->get('error_upload');

					break;
				}

				$name = pathinfo($filename, PATHINFO_FILENAME);
				$ext = strtolower(strrchr($filename, '.'));
				$target = $directory . '/' . $name . $ext;

				for ($n = 2; is_file($target); $n++) {
					$target = $directory . '/' . $name . '-' . $n . $ext;
				}

				$moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $target) : ($file['tmp_name'] === $this->downloaded_tmp && (rename($file['tmp_name'], $target) || (copy($file['tmp_name'], $target) && unlink($file['tmp_name']))));

				if (!$moved) {
					$json['error'] = $this->language->get('error_upload');

					break;
				}

				$uploaded[] = $target;
			}
		}

		if (!$json) {
			$this->load->model('tool/image');

			$json['success'] = $this->language->get('text_uploaded');
			$json['files'] = array();

			foreach ($uploaded as $path) {
				$relative = utf8_substr($path, utf8_strlen(DIR_IMAGE));

				$json['files'][] = array('path' => $relative, 'thumb' => $this->model_tool_image->resize($relative, 100, 100));
			}
		}

		$this->json($json);
	}

	public function download() {
		$this->load->language('common/filemanager');
		$this->load->language('catalog/bmedia');

		$error = '';

		if (!$this->allowed()) {
			$error = $this->language->get('error_permission');
		}

		$url = isset($this->request->post['url']) ? trim(html_entity_decode($this->request->post['url'], ENT_QUOTES, 'UTF-8')) : '';

		if (!$error && !preg_match('#^https?://#i', $url)) {
			$error = $this->language->get('error_url');
		}

		if (!$error) {
			$result = $this->fetchRemoteImage($url, (int)$this->config->get('config_file_max_size'));

			if (!empty($result['error'])) {
				$error = $this->language->get($result['error']);
			}
		}

		if ($error) {
			$this->json(array('error' => $error));

			return;
		}

		$this->downloaded_tmp = $result['tmp'];

		$this->request->files['file'] = array(
			'name'     => array($result['name']),
			'type'     => array($result['mime']),
			'tmp_name' => array($result['tmp']),
			'error'    => array(0),
			'size'     => array(filesize($result['tmp']))
		);

		$this->upload();

		if (is_file($result['tmp'])) {
			@unlink($result['tmp']);
		}
	}

	private function fetchRemoteImage($url, $max_size) {
		$mimes = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');

		if (!function_exists('curl_init')) {
			return array('error' => 'error_download');
		}

		$tmp = tempnam(sys_get_temp_dir(), 'bpm');

		for ($hop = 0; $hop <= 3; $hop++) {
			$parts = parse_url($url);

			if (!$parts || empty($parts['host']) || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'))) {
				@unlink($tmp);

				return array('error' => 'error_url');
			}

			$scheme = strtolower($parts['scheme']);
			$host = $parts['host'];
			$port = isset($parts['port']) ? (int)$parts['port'] : ($scheme == 'https' ? 443 : 80);

			if (!in_array($port, array(80, 443))) {
				@unlink($tmp);

				return array('error' => 'error_url');
			}

			// Block private and reserved addresses, then pin the validated IP to avoid DNS rebinding
			$ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : (array)gethostbynamel($host);
			$ip = '';

			foreach ($ips as $candidate) {
				if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					$ip = $candidate;
				} else {
					$ip = '';

					break;
				}
			}

			if (!$ip) {
				@unlink($tmp);

				return array('error' => 'error_url');
			}

			$handle = fopen($tmp, 'wb');
			$written = 0;
			$location = '';
			$oversize = false;

			$ch = curl_init($url);

			curl_setopt_array($ch, array(
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 8,
				CURLOPT_TIMEOUT        => 25,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; QuickProductManager)',
				CURLOPT_RESOLVE        => array($host . ':' . $port . ':' . $ip),
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$location) {
					if (stripos($header, 'location:') === 0) {
						$location = trim(substr($header, 9));
					}

					return strlen($header);
				},
				CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($handle, &$written, &$oversize, $max_size) {
					$written += strlen($chunk);

					if ($max_size && $written > $max_size) {
						$oversize = true;

						return 0;
					}

					return fwrite($handle, $chunk);
				}
			));

			curl_exec($ch);

			$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);
			fclose($handle);

			if ($oversize) {
				@unlink($tmp);

				return array('error' => 'error_filesize');
			}

			if ($status >= 300 && $status < 400 && $location) {
				if (preg_match('#^https?://#i', $location)) {
					$url = $location;
				} elseif (strpos($location, '//') === 0) {
					$url = $scheme . ':' . $location;
				} elseif (strpos($location, '/') === 0) {
					$url = $scheme . '://' . $host . ($port != 80 && $port != 443 ? ':' . $port : '') . $location;
				} else {
					@unlink($tmp);

					return array('error' => 'error_download');
				}

				continue;
			}

			if ($status != 200 || !$written) {
				@unlink($tmp);

				return array('error' => 'error_download');
			}

			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$mime = $finfo->file($tmp);

			if (!isset($mimes[$mime]) || !@getimagesize($tmp)) {
				@unlink($tmp);

				return array('error' => 'error_filetype');
			}

			$name = urldecode(basename((string)parse_url($url, PHP_URL_PATH)));
			$base = pathinfo(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), PATHINFO_FILENAME);

			if (utf8_strlen($base) < 1) {
				$base = 'image';
			}

			return array('tmp' => $tmp, 'mime' => $mime, 'name' => $base . '.' . $mimes[$mime]);
		}

		@unlink($tmp);

		return array('error' => 'error_download');
	}
}
