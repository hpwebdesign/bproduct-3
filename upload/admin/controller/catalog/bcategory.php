<?php
/**
 * Quick Category Manager (bproduct)
 *
 * Replaces the default category list through an OCMOD hook in catalog/category::index().
 * Writes go through the default catalog/category model (addCategory, editCategory, deleteCategory).
 * The extra model adds list queries, bulk helpers and the Display Name storage.
 *
 * Requests: index.php?route=catalog/category&user_token=...&bc=<action>
 * The default catalog/category permission is reused, so no extra permission has to be granted.
 */
class ControllerCatalogBcategory extends Controller {
	private $actions = array('list', 'category', 'save', 'add', 'delete', 'status', 'bulk', 'ac', 'seo', 'slugauto', 'media');

	public function index() {
		$this->load->language('catalog/bcategory');

		$bc = isset($this->request->get['bc']) ? $this->request->get['bc'] : '';

		if (!$this->config->get('module_bproduct_status') || !$this->config->get('module_bproduct_category_status')) {
			if ($bc === '') {
				$this->response->redirect($this->url->link('catalog/category', 'user_token=' . $this->session->data['user_token'] . '&classic=1', true));
			} else {
				$this->json(array('error' => $this->language->get('bc_error_disabled')));
			}

			return;
		}

		if ($bc === '') {
			$this->page();

			return;
		}

		if (!in_array($bc, $this->actions)) {
			$this->json(array('error' => $this->language->get('bc_error_invalid')));

			return;
		}

		if (!$this->user->hasPermission('access', 'catalog/category')) {
			$this->deny();

			return;
		}

		$method = 'ajax' . ucfirst($bc);

		$this->$method();
	}

	// Image popover requests (limits, upload, download) go through this route so the default category permission is enough
	private function ajaxMedia() {
		$op = isset($this->request->get['op']) ? $this->request->get['op'] : '';

		if (in_array($op, array('limits', 'upload', 'download'))) {
			$this->load->controller('catalog/bmedia/' . $op);
		} else {
			$this->json(array('error' => $this->language->get('bc_error_invalid')));
		}
	}

	private function page() {
		$this->document->setTitle($this->language->get('heading_title'));

		$token = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $token)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/category', 'user_token=' . $token)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['classic'] = str_replace('&amp;', '&', $this->url->link('catalog/category', 'user_token=' . $token . '&classic=1'));
		$data['add_full'] = str_replace('&amp;', '&', $this->url->link('catalog/category/add', 'user_token=' . $token));

		$data['bc_lang'] = $this->encodeForScript($this->language->all());
		$data['bc_config'] = $this->encodeForScript($this->getConfig());

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/bcategory', $data));
	}

	private function encodeForScript($value) {
		return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	private function getConfig() {
		$token = $this->session->data['user_token'];

		$this->load->model('localisation/language');
		$this->load->model('setting/store');
		$this->load->model('design/layout');
		$this->load->model('tool/image');

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if ($language['status']) {
				$languages[] = array('language_id' => (int)$language['language_id'], 'code' => $language['code'], 'name' => $language['name']);
			}
		}

		$stores = array(array('store_id' => 0, 'name' => $this->decode($this->config->get('config_name')) . ' ' . trim(strip_tags($this->language->get('text_default')))));

		foreach ($this->model_setting_store->getStores() as $store) {
			$stores[] = array('store_id' => (int)$store['store_id'], 'name' => $this->decode($store['name']));
		}

		$layouts = array();

		foreach ($this->model_design_layout->getLayouts() as $layout) {
			$layouts[] = array('id' => (int)$layout['layout_id'], 'name' => $this->decode($layout['name']));
		}

		$catalog = isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1')) ? HTTPS_CATALOG : HTTP_CATALOG;

		$limit = (int)$this->config->get('module_bproduct_limit');

		return array(
			'endpoint'     => 'index.php?route=catalog/category&user_token=' . $token . '&bc=',
			'edit_url'     => 'index.php?route=catalog/category/edit&user_token=' . $token . '&category_id=',
			'filemanager'  => 'index.php?route=common/filemanager&user_token=' . $token,
			'catalog_url'  => rtrim($catalog, '/'),
			'storefront_url' => $catalog . 'index.php?route=product/category&path=',
			'image_base'   => $catalog . 'image/',
			'placeholder'  => $this->model_tool_image->resize('no_image.png', 100, 100),
			'language_id'  => (int)$this->config->get('config_language_id'),
			'languages'    => $languages,
			'stores'       => $stores,
			'layouts'      => $layouts,
			'limit'        => in_array($limit, array(5, 10, 25, 50, 100)) ? $limit : 10,
			'display_on'   => (int)$this->config->get('module_bproduct_category_display'),
			'complete_seo' => $this->completeSeo() ? 1 : 0,
			'extra_parents' => $this->hasExtraParents() ? 1 : 0,
			'can_modify'   => $this->user->hasPermission('modify', 'catalog/category') ? 1 : 0
		);
	}

	/* --------------------------------------------------------------- helpers */

	private function hasExtraParents() {
		return (bool)$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "category` LIKE 'extra_parents'")->num_rows;
	}

	private function extraParentIds($info) {
		$ids = array();

		if (!empty($info['extra_parents'])) {
			foreach (explode('|', $info['extra_parents']) as $id) {
				if ((int)$id > 0) {
					$ids[] = (int)$id;
				}
			}
		}

		return array_values(array_unique($ids));
	}

	// Complete SEO writes the SEO URL from seo_keyword when its own URL handling is enabled
	private function withSeoKeyword($data) {
		if (!$this->completeSeo()) {
			return $data;
		}

		foreach ($data['category_description'] as $language_id => $value) {
			$data['category_description'][$language_id]['seo_keyword'] = isset($data['category_seo_url'][0][$language_id]) ? $data['category_seo_url'][0][$language_id] : '';
		}

		return $data;
	}

	private function completeSeo() {
		if ((int)$this->config->get('module_complete_seo_status') !== 1) {
			return false;
		}

		return (bool)$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "category_description` LIKE 'seo_h1'")->num_rows;
	}

	private function json($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function isPost() {
		return $this->request->server['REQUEST_METHOD'] == 'POST';
	}

	// Raw JSON body. It bypasses the request cleaner, so values are encoded explicitly in encode().
	private function input() {
		$data = json_decode(file_get_contents('php://input'), true);

		return is_array($data) ? $data : array();
	}

	private function canModify() {
		return $this->isPost() && $this->user->hasPermission('modify', 'catalog/category');
	}

	private function deny() {
		$this->json(array('error' => $this->language->get('bc_error_permission')));
	}

	// OpenCart stores admin input HTML-escaped (what Request::clean() produces).
	private function encode($value) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->encode($item);
			}

			return $value;
		}

		return is_string($value) ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value;
	}

	private function decode($value) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->decode($item);
			}

			return $value;
		}

		return is_string($value) ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
	}

	private function cleanPath($path) {
		return preg_replace('/[\s\x{00A0}]*>[\s\x{00A0}]*/u', ' > ', $this->decode($path));
	}

	private function thumb($image, $w = 100, $h = 100) {
		$this->load->model('tool/image');

		if ($image && is_file(DIR_IMAGE . $image)) {
			return $this->model_tool_image->resize($image, $w, $h);
		}

		return $this->model_tool_image->resize('no_image.png', $w, $h);
	}

	private function ids($list) {
		$ids = array();

		if (is_array($list)) {
			foreach ($list as $id) {
				if ((int)$id > 0) {
					$ids[] = (int)$id;
				}
			}
		}

		return array_values(array_unique($ids));
	}

	private function slugify($text) {
		$text = $this->decode($text);

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

			if ($converted !== false) {
				$text = $converted;
			}
		}

		$text = strtolower($text);
		$text = preg_replace('/[^a-z0-9]+/', '-', $text);

		return trim($text, '-');
	}

	private function keywordTaken($keyword, $store_id, $category_id) {
		$this->load->model('design/seo_url');

		foreach ($this->model_design_seo_url->getSeoUrlsByKeyword($keyword) as $seo_url) {
			if ($seo_url['store_id'] == $store_id && (!$category_id || $seo_url['query'] != 'category_id=' . (int)$category_id)) {
				return true;
			}
		}

		return false;
	}

	private function activeLanguages() {
		$this->load->model('localisation/language');

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if ($language['status']) {
				$languages[] = (int)$language['language_id'];
			}
		}

		return $languages;
	}

	/* ------------------------------------------------------------------ list */

	private function formatRows($rows) {
		$ids = array();

		foreach ($rows as $row) {
			$ids[] = (int)$row['category_id'];
		}

		$counts = $this->model_catalog_bcategory->getProductCounts($ids);
		$slugs = $this->model_catalog_bcategory->getSeoKeywords($ids);
		$displays = $this->model_catalog_bcategory->getDisplayNames($ids);

		$this->load->model('tool/image');

		$result = array();

		foreach ($rows as $row) {
			$id = (int)$row['category_id'];
			$parts = explode(' > ', $this->cleanPath($row['name']));
			$leaf = array_pop($parts);

			$result[] = array(
				'category_id' => $id,
				'leaf'        => $leaf,
				'path'        => implode(' > ', $parts),
				'level'       => (int)$row['level'],
				'parent_id'   => (int)$row['parent_id'],
				'status'      => (int)$row['status'],
				'top'         => (int)$row['top'],
				'sort_order'  => (int)$row['sort_order'],
				'products'    => isset($counts[$id]) ? $counts[$id] : 0,
				'slug'        => isset($slugs[$id]) ? $this->decode($slugs[$id]) : '',
				'display'     => isset($displays[$id]) ? $this->decode($displays[$id]) : '',
				'thumb'       => !empty($row['image']) ? (string)$this->model_tool_image->resize($row['image'], 68, 68) : ''
			);
		}

		return $result;
	}

	private function rowFor($category_id) {
		$rows = $this->formatRows($this->model_catalog_bcategory->getCategories(array('filter_ids' => array($category_id))));

		return $rows ? $rows[0] : null;
	}

	private function ajaxList() {
		$this->load->model('catalog/bcategory');

		$get = $this->request->get;

		$limit = isset($get['limit']) && in_array((int)$get['limit'], array(5, 10, 20, 25, 50, 100)) ? (int)$get['limit'] : 10;
		$page = max(1, isset($get['page']) ? (int)$get['page'] : 1);

		$filter = array(
			'filter_q'         => isset($get['q']) ? html_entity_decode(trim($get['q']), ENT_QUOTES, 'UTF-8') : '',
			'filter_status'    => isset($get['status']) ? $get['status'] : '',
			'filter_parent_id' => isset($get['parent_id']) ? (int)$get['parent_id'] : 0,
			'sort'             => isset($get['sort']) ? $get['sort'] : 'name',
			'order'            => isset($get['order']) ? $get['order'] : 'ASC'
		);

		$total = $this->model_catalog_bcategory->getTotalCategories($filter);
		$pages = max(1, (int)ceil($total / $limit));
		$page = min($page, $pages);

		$filter['start'] = ($page - 1) * $limit;
		$filter['limit'] = $limit;

		$this->json(array(
			'categories' => $this->formatRows($this->model_catalog_bcategory->getCategories($filter)),
			'total'      => $total,
			'page'       => $page,
			'pages'      => $pages,
			'stats'      => $this->model_catalog_bcategory->getStats()
		));
	}

	/* ---------------------------------------------------------------- editor */

	private function loadFull($category_id, $info) {
		$this->load->model('catalog/category');

		$full = array(
			'parent_id'            => (int)$info['parent_id'],
			'top'                  => (int)$info['top'],
			'column'               => (int)$info['column'],
			'sort_order'           => (int)$info['sort_order'],
			'status'               => (int)$info['status'],
			'image'                => $info['image'],
			'category_description' => $this->model_catalog_category->getCategoryDescriptions($category_id),
			'category_filter'      => $this->model_catalog_category->getCategoryFilters($category_id),
			'category_store'       => $this->model_catalog_category->getCategoryStores($category_id),
			'category_seo_url'     => $this->model_catalog_category->getCategorySeoUrls($category_id),
			'category_layout'      => $this->model_catalog_category->getCategoryLayouts($category_id)
		);

		// Always passed back so the Multiple Parent Category hook never resets it on save
		if ($this->hasExtraParents()) {
			$full['extra_parents'] = $this->extraParentIds($info);
		}

		return $full;
	}

	private function ajaxCategory() {
		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');
		$this->load->model('catalog/filter');

		$category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
		$info = $this->model_catalog_category->getCategory($category_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bc_error_not_found')));

			return;
		}

		$full = $this->loadFull($category_id, $info);

		$descriptions = array();

		foreach ($this->activeLanguages() as $language_id) {
			$d = isset($full['category_description'][$language_id]) ? $full['category_description'][$language_id] : array();

			$descriptions[$language_id] = array(
				'name'             => isset($d['name']) ? $this->decode($d['name']) : '',
				'description'      => isset($d['description']) ? $this->decode($d['description']) : '',
				'meta_title'       => isset($d['meta_title']) ? $this->decode($d['meta_title']) : '',
				'meta_description' => isset($d['meta_description']) ? $this->decode($d['meta_description']) : '',
				'meta_keyword'     => isset($d['meta_keyword']) ? $this->decode($d['meta_keyword']) : ''
			);

			if ($this->completeSeo()) {
				foreach (array('seo_h1', 'seo_h2', 'seo_h3') as $key) {
					$descriptions[$language_id][$key] = isset($d[$key]) ? $this->decode($d[$key]) : '';
				}
			}
		}

		$display = array();

		foreach ($this->model_catalog_bcategory->getDisplayNamesByCategory($category_id) as $language_id => $name) {
			$display[$language_id] = $this->decode($name);
		}

		$parent = null;

		if ($full['parent_id']) {
			$parent_info = $this->model_catalog_category->getCategory($full['parent_id']);

			if ($parent_info) {
				$parent = array('id' => (int)$full['parent_id'], 'name' => $this->cleanPath($parent_info['name']));
			}
		}

		$filters = array();

		foreach ($full['category_filter'] as $filter_id) {
			$filter_info = $this->model_catalog_filter->getFilter($filter_id);

			if ($filter_info) {
				$filters[] = array('id' => (int)$filter_id, 'name' => $this->decode($filter_info['group'] . ' > ' . $filter_info['name']));
			}
		}

		$extra = array();

		if (isset($full['extra_parents'])) {
			foreach ($full['extra_parents'] as $extra_id) {
				$extra_info = $this->model_catalog_category->getCategory($extra_id);

				if ($extra_info) {
					$extra[] = array('id' => (int)$extra_id, 'name' => $this->cleanPath(!empty($extra_info['path']) ? $extra_info['path'] : $extra_info['name']));
				}
			}
		}

		$seo = array();

		foreach ($full['category_seo_url'] as $store_id => $languages) {
			foreach ($languages as $language_id => $keyword) {
				$seo[(int)$store_id][(int)$language_id] = $this->decode($keyword);
			}
		}

		$layouts = array();

		foreach ($full['category_layout'] as $store_id => $layout_id) {
			$layouts[(int)$store_id] = (int)$layout_id;
		}

		$this->json(array('category' => array(
			'category_id' => $category_id,
			'description' => $descriptions,
			'display'     => $display,
			'data'        => array(
				'parent_id'  => $full['parent_id'],
				'top'        => $full['top'],
				'column'     => $full['column'],
				'sort_order' => $full['sort_order'],
				'status'     => $full['status']
			),
			'parent'      => $parent,
			'image'       => array('path' => $this->decode($full['image']), 'thumb' => $this->thumb($full['image'])),
			'stores'      => array_map('intval', $full['category_store']),
			'filters'     => $filters,
			'extra_parents' => $extra,
			'seo'         => $seo,
			'layouts'     => $layouts
		)));
	}

	private function mergeSections($data, $sections) {
		if (isset($sections['general']['descriptions']) && is_array($sections['general']['descriptions'])) {
			foreach ($sections['general']['descriptions'] as $language_id => $value) {
				$keys = array('name', 'description', 'meta_title', 'meta_description', 'meta_keyword');

				if ($this->completeSeo()) {
					$keys = array_merge($keys, array('seo_h1', 'seo_h2', 'seo_h3'));
				}
				$current = isset($data['category_description'][(int)$language_id]) ? $data['category_description'][(int)$language_id] : array_fill_keys($keys, '');

				foreach ($keys as $key) {
					if (isset($value[$key])) {
						$current[$key] = $key == 'name' || $key == 'meta_title' ? trim($value[$key]) : $value[$key];
					}
				}

				$data['category_description'][(int)$language_id] = $current;
			}
		}

		if (isset($sections['data'])) {
			$d = $sections['data'];

			foreach (array('parent_id', 'top', 'column', 'sort_order', 'status') as $key) {
				if (isset($d[$key])) {
					$data[$key] = $key == 'top' || $key == 'status' ? (!empty($d[$key]) ? 1 : 0) : max(0, (int)$d[$key]);
				}
			}

			if (isset($d['image'])) {
				$data['image'] = (string)$d['image'];
			}

			if (isset($d['stores'])) {
				$stores = array();

				foreach ((array)$d['stores'] as $store_id) {
					if (is_numeric($store_id)) {
						$stores[] = (int)$store_id;
					}
				}

				$data['category_store'] = array_values(array_unique($stores));
			}

			if (isset($d['filters'])) {
				$data['category_filter'] = $this->ids($d['filters']);
			}

			if (isset($d['extra_parents']) && isset($data['extra_parents'])) {
				$data['extra_parents'] = $this->ids($d['extra_parents']);
			}
		}

		if (isset($sections['seo']['keywords']) && is_array($sections['seo']['keywords'])) {
			foreach ($sections['seo']['keywords'] as $store_id => $languages) {
				foreach ((array)$languages as $language_id => $keyword) {
					$data['category_seo_url'][(int)$store_id][(int)$language_id] = trim($keyword);
				}
			}
		}

		if (isset($sections['design']['layouts']) && is_array($sections['design']['layouts'])) {
			foreach ($sections['design']['layouts'] as $store_id => $layout_id) {
				$data['category_layout'][(int)$store_id] = (int)$layout_id;
			}
		}

		return $data;
	}

	private function validate($data, $sections, $category_id) {
		$error = array();

		if (isset($sections['general']['descriptions'])) {
			foreach ($sections['general']['descriptions'] as $language_id => $value) {
				$value = $data['category_description'][(int)$language_id];

				if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
					$error['name'][$language_id] = $this->language->get('bc_error_name');
				}

				if ((utf8_strlen($value['meta_title']) < 1) || (utf8_strlen($value['meta_title']) > 255)) {
					$error['meta_title'][$language_id] = $this->language->get('bc_error_meta_title');
				}
			}
		}

		if (isset($sections['general']['display']) && is_array($sections['general']['display'])) {
			foreach ($sections['general']['display'] as $language_id => $name) {
				if (utf8_strlen(html_entity_decode($name, ENT_QUOTES, 'UTF-8')) > 255) {
					$error['display'][$language_id] = $this->language->get('bc_error_display_name');
				}
			}
		}

		if (isset($sections['data']['parent_id']) && $data['parent_id']) {
			if ($this->model_catalog_bcategory->isInSubtree($data['parent_id'], $category_id)) {
				$error['parent'] = $this->language->get('bc_error_parent');
			}
		}

		if (isset($sections['data']['extra_parents']) && !empty($data['extra_parents']) && in_array((int)$category_id, $data['extra_parents'])) {
			$error['parent'] = $this->language->get('bc_error_extra_self');
		}

		if (isset($sections['seo']['keywords'])) {
			foreach ($sections['seo']['keywords'] as $store_id => $languages) {
				foreach ((array)$languages as $language_id => $keyword) {
					$keyword = $data['category_seo_url'][(int)$store_id][(int)$language_id];

					if ($keyword === '') {
						continue;
					}

					if (count(array_keys($data['category_seo_url'][(int)$store_id], $keyword)) > 1) {
						$error['keyword'][$store_id][$language_id] = $this->language->get('bc_error_unique');
					} elseif ($this->keywordTaken($keyword, (int)$store_id, $category_id)) {
						$error['keyword'][$store_id][$language_id] = $this->language->get('bc_error_keyword');
					}
				}
			}
		}

		return $error;
	}

	private function cleanDisplay($names) {
		$result = array();

		if (is_array($names)) {
			foreach ($names as $language_id => $name) {
				$result[(int)$language_id] = trim((string)$name);
			}
		}

		return $result;
	}

	private function ajaxSave() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');

		$input = $this->input();

		$category_id = isset($input['category_id']) ? (int)$input['category_id'] : 0;
		$info = $this->model_catalog_category->getCategory($category_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bc_error_not_found')));

			return;
		}

		$sections = isset($input['sections']) && is_array($input['sections']) ? $this->encode($input['sections']) : array();

		if (isset($sections['general']['display'])) {
			$sections['general']['display'] = $this->cleanDisplay($sections['general']['display']);
		}

		$data = $this->mergeSections($this->loadFull($category_id, $info), $sections);

		$error = $this->validate($data, $sections, $category_id);

		if ($error) {
			$error['warning'] = $this->language->get('bc_error_warning');

			$this->json(array('error' => $error));

			return;
		}

		$this->model_catalog_category->editCategory($category_id, $this->withSeoKeyword($data));

		if (isset($sections['general']['display'])) {
			$this->model_catalog_bcategory->saveDisplayNames($category_id, $sections['general']['display']);
		}

		$this->json(array('success' => $this->language->get('bc_text_saved'), 'row' => $this->rowFor($category_id), 'stats' => $this->model_catalog_bcategory->getStats()));
	}

	private function uniqueKeyword($base, $store_id, $used) {
		$keyword = $base;
		$n = 2;

		while (in_array($keyword, $used) || $this->keywordTaken($keyword, $store_id, 0)) {
			$keyword = $base . '-' . $n++;
		}

		return $keyword;
	}

	private function ajaxAdd() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');

		$input = $this->encode($this->input());

		$name = isset($input['name']) ? trim($input['name']) : '';
		$parent_id = isset($input['parent_id']) ? max(0, (int)$input['parent_id']) : 0;

		$error = array();

		if ((utf8_strlen($name) < 1) || (utf8_strlen($name) > 255)) {
			$error['name'] = $this->language->get('bc_error_name');
		}

		if ($parent_id && !$this->model_catalog_category->getCategory($parent_id)) {
			$error['parent'] = $this->language->get('bc_error_parent');
		}

		if ($error) {
			$error['warning'] = $this->language->get('bc_error_warning');

			$this->json(array('error' => $error));

			return;
		}

		$languages = $this->activeLanguages();

		$data = array(
			'parent_id'            => $parent_id,
			'top'                  => 0,
			'column'               => 1,
			'sort_order'           => 0,
			'status'               => !empty($input['status']) ? 1 : 0,
			'image'                => isset($input['image']) ? (string)$input['image'] : '',
			'category_store'       => array(0),
			'category_filter'      => array(),
			'category_layout'      => array(),
			'category_description' => array(),
			'category_seo_url'     => array()
		);

		$base = $this->slugify($name);
		$used = array();

		foreach ($languages as $language_id) {
			$data['category_description'][$language_id] = array(
				'name'             => $name,
				'description'      => '',
				'meta_title'       => $name,
				'meta_description' => '',
				'meta_keyword'     => ''
			);

			if ($base !== '') {
				$keyword = $this->uniqueKeyword($base, 0, $used);
				$used[] = $keyword;
				$data['category_seo_url'][0][$language_id] = $keyword;
			}
		}

		$category_id = (int)$this->model_catalog_category->addCategory($this->withSeoKeyword($data));

		$display = isset($input['display_name']) ? trim($input['display_name']) : '';

		if ($display !== '' && utf8_strlen(html_entity_decode($display, ENT_QUOTES, 'UTF-8')) <= 255) {
			$names = array();

			foreach ($languages as $language_id) {
				$names[$language_id] = $display;
			}

			$this->model_catalog_bcategory->saveDisplayNames($category_id, $names);
		}

		$this->json(array('success' => $this->language->get('bc_text_added'), 'category_id' => $category_id, 'row' => $this->rowFor($category_id), 'stats' => $this->model_catalog_bcategory->getStats()));
	}

	/* ---------------------------------------------------------------- actions */

	private function ajaxDelete() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());

		$removed = array();

		foreach ($ids as $category_id) {
			$removed = array_merge($removed, $this->model_catalog_bcategory->getSubtreeIds($category_id));

			$this->model_catalog_category->deleteCategory($category_id);
		}

		$this->model_catalog_bcategory->deleteDisplayNames($removed);

		$this->json(array('success' => sprintf($this->language->get('bc_text_deleted'), count($ids)), 'stats' => $this->model_catalog_bcategory->getStats()));
	}

	private function ajaxStatus() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/bcategory');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());
		$status = !empty($input['status']) ? 1 : 0;

		$this->model_catalog_bcategory->editStatus($ids, $status);

		$this->json(array('success' => sprintf($this->language->get($status ? 'bc_text_enabled' : 'bc_text_disabled'), count($ids)), 'stats' => $this->model_catalog_bcategory->getStats()));
	}

	private function ajaxBulk() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());
		$value = isset($input['value']) ? (int)$input['value'] : 0;
		$action = isset($input['action']) ? $input['action'] : '';

		$done = count($ids);

		if ($action == 'top') {
			$this->model_catalog_bcategory->editTop($ids, $value ? 1 : 0);
		} elseif ($action == 'parent') {
			if ($value && !$this->model_catalog_category->getCategory($value)) {
				$this->json(array('error' => $this->language->get('bc_error_parent')));

				return;
			}

			$done = 0;

			foreach ($ids as $category_id) {
				if (!$value || !$this->model_catalog_bcategory->isInSubtree($value, $category_id)) {
					$this->model_catalog_bcategory->editParent($category_id, $value);
					$done++;
				}
			}

			if ($done) {
				$this->model_catalog_category->repairCategories();
				$this->cache->delete('category');
			}
		} else {
			$this->json(array('error' => $this->language->get('bc_error_invalid')));

			return;
		}

		$message = sprintf($this->language->get('bc_text_updated'), $done);

		if ($done < count($ids)) {
			$message .= ' ' . sprintf($this->language->get('bc_text_skipped'), count($ids) - $done);
		}

		$this->json(array('success' => $message, 'stats' => $this->model_catalog_bcategory->getStats()));
	}

	private function ajaxAc() {
		$type = isset($this->request->get['type']) ? $this->request->get['type'] : '';
		$q = isset($this->request->get['q']) ? trim($this->request->get['q']) : '';
		$exclude = isset($this->request->get['exclude']) ? (int)$this->request->get['exclude'] : 0;
		$limit = 15;
		$items = array();

		if ($type == 'category') {
			$this->load->model('catalog/category');
			$this->load->model('catalog/bcategory');

			$skip = $exclude ? $this->model_catalog_bcategory->getSubtreeIds($exclude) : array();

			foreach ($this->model_catalog_category->getCategories(array('filter_name' => $q, 'sort' => 'name', 'start' => 0, 'limit' => $limit + count($skip))) as $row) {
				if (!in_array((int)$row['category_id'], $skip) && count($items) < $limit) {
					$items[] = array('id' => (int)$row['category_id'], 'name' => $this->cleanPath($row['name']));
				}
			}
		} elseif ($type == 'filter') {
			$this->load->model('catalog/filter');

			foreach ($this->model_catalog_filter->getFilters(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
				$items[] = array('id' => (int)$row['filter_id'], 'name' => $this->decode($row['group'] . ' > ' . $row['name']));
			}
		}

		$this->json(array('items' => $items));
	}

	private function ajaxSeo() {
		$keyword = isset($this->request->get['keyword']) ? trim(html_entity_decode($this->request->get['keyword'], ENT_QUOTES, 'UTF-8')) : '';
		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
		$category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;

		$json = array('ok' => 1);

		if ($keyword !== '' && $this->keywordTaken(htmlspecialchars($keyword, ENT_COMPAT, 'UTF-8'), $store_id, $category_id)) {
			$json['ok'] = 0;

			$base = $this->slugify($keyword);
			$n = 2;
			$suggest = $base . '-' . $n;

			while ($this->keywordTaken($suggest, $store_id, $category_id)) {
				$suggest = $base . '-' . (++$n);
			}

			$json['suggest'] = $suggest;
		}

		$this->json($json);
	}

	private function ajaxSlugauto() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/category');
		$this->load->model('catalog/bcategory');
		$this->load->model('setting/store');

		$input = $this->input();

		$category_id = isset($input['category_id']) ? (int)$input['category_id'] : 0;
		$info = $this->model_catalog_category->getCategory($category_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bc_error_not_found')));

			return;
		}

		$data = $this->loadFull($category_id, $info);
		$generated = 0;

		$stores = array(0);

		foreach ($this->model_setting_store->getStores() as $store) {
			$stores[] = (int)$store['store_id'];
		}

		foreach ($stores as $store_id) {
			$used = array();

			foreach ($this->activeLanguages() as $language_id) {
				if (!empty($data['category_seo_url'][$store_id][$language_id])) {
					$used[] = $data['category_seo_url'][$store_id][$language_id];

					continue;
				}

				$base = isset($data['category_description'][$language_id]) ? $this->slugify($data['category_description'][$language_id]['name']) : '';

				if ($base === '') {
					continue;
				}

				$keyword = $base;
				$n = 2;

				while (in_array($keyword, $used) || $this->keywordTaken($keyword, $store_id, $category_id)) {
					$keyword = $base . '-' . $n++;
				}

				$used[] = $keyword;
				$data['category_seo_url'][$store_id][$language_id] = $keyword;
				$generated++;
			}
		}

		if (!$generated) {
			$this->json(array('error' => $this->language->get('bc_slug_empty')));

			return;
		}

		$this->model_catalog_category->editCategory($category_id, $this->withSeoKeyword($data));

		$this->json(array('success' => 1, 'row' => $this->rowFor($category_id), 'stats' => $this->model_catalog_bcategory->getStats()));
	}
}
