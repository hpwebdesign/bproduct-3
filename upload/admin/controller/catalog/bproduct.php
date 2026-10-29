<?php
/**
 * Quick Product Manager (bproduct)
 *
 * Replaces the default product list through an OCMOD hook in catalog/product::index().
 * All writes go through the default catalog/product model (editProduct, addProduct,
 * deleteProduct, copyProduct). The extra model only adds list queries and bulk helpers.
 *
 * Requests: index.php?route=catalog/product&user_token=...&bp=<action>
 * The default catalog/product permission is reused, so no extra permission has to be granted.
 */
class ControllerCatalogBproduct extends Controller {
	private $actions = array('list', 'product', 'save', 'add', 'delete', 'copy', 'status', 'bulk', 'ac', 'seo', 'slugauto');

	private $scalars = array('model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location', 'quantity', 'minimum', 'subtract', 'stock_status_id', 'date_available', 'shipping', 'price', 'weight', 'weight_class_id', 'length', 'width', 'height', 'length_class_id', 'status', 'tax_class_id', 'sort_order');

	// Optional oc_product columns added by the store's own product OCMOD; only handled when the column exists
	private $optional_columns = array('frontend_demo_link', 'backend_demo_link', 'documentation_link', 'extension_id');

	private $extra_cache = null;

	private function extraKeys() {
		if ($this->extra_cache === null) {
			$this->extra_cache = array();

			foreach ($this->optional_columns as $column) {
				$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product` LIKE '" . $this->db->escape($column) . "'");

				if ($query->num_rows) {
					$this->extra_cache[] = $column;
				}
			}
		}

		return $this->extra_cache;
	}

	private function barcodeSource() {
		return in_array($this->config->get('module_bproduct_barcode_source'), array('model', 'sku')) ? $this->config->get('module_bproduct_barcode_source') : 'model';
	}

	// Returns an error message when the barcode source value is already used by another product
	private function barcodeError($value, $product_id) {
		if (!$this->config->get('module_bproduct_barcode_status') || $value === '') {
			return '';
		}

		$source = $this->barcodeSource();

		if ($this->model_catalog_bproduct->isFieldTaken($source, $value, $product_id)) {
			return $this->language->get('bp_error_unique_' . $source);
		}

		return '';
	}

	private function scalarKeys() {
		return array_merge($this->scalars, $this->extraKeys());
	}

	public function index() {
		$this->load->language('catalog/product');
		$this->load->language('catalog/bproduct');

		$bp = isset($this->request->get['bp']) ? $this->request->get['bp'] : '';

		if (!$this->config->get('module_bproduct_status')) {
			if ($bp === '') {
				$this->response->redirect($this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . '&classic=1', true));
			} else {
				$this->json(array('error' => $this->language->get('bp_error_disabled')));
			}

			return;
		}

		if ($bp === '') {
			$this->page();

			return;
		}

		if (in_array($bp, $this->actions)) {
			$this->{'ajax' . ucfirst($bp)}();
		} else {
			$this->json(array('error' => $this->language->get('bp_error_invalid')));
		}
	}

	/* ------------------------------------------------------------------ page */

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
			'href' => $this->url->link('catalog/product', 'user_token=' . $token)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['classic'] = str_replace('&amp;', '&', $this->url->link('catalog/product', 'user_token=' . $token . '&classic=1'));
		$data['add_full'] = str_replace('&amp;', '&', $this->url->link('catalog/product/add', 'user_token=' . $token));
		$data['button_classic'] = $this->language->get('bp_button_classic');
		$data['button_add_full'] = $this->language->get('bp_button_add_full');

		$data['bp_lang'] = $this->encodeForScript($this->language->all());
		$data['bp_config'] = $this->encodeForScript($this->getConfig());

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/bproduct', $data));
	}

	private function encodeForScript($value) {
		return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	private function getConfig() {
		$token = $this->session->data['user_token'];

		if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
			$catalog = HTTPS_CATALOG;
		} else {
			$catalog = HTTP_CATALOG;
		}

		$this->load->model('localisation/language');
		$this->load->model('setting/store');
		$this->load->model('customer/customer_group');
		$this->load->model('localisation/stock_status');
		$this->load->model('localisation/tax_class');
		$this->load->model('localisation/length_class');
		$this->load->model('localisation/weight_class');
		$this->load->model('design/layout');
		$this->load->model('tool/image');

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if ($language['status']) {
				$languages[] = array('language_id' => (int)$language['language_id'], 'code' => $language['code'], 'name' => $language['name']);
			}
		}

		$stores = array(array('store_id' => 0, 'name' => $this->decode($this->config->get('config_name')) . ' (' . $this->language->get('text_default') . ')'));

		foreach ($this->model_setting_store->getStores() as $store) {
			$stores[] = array('store_id' => (int)$store['store_id'], 'name' => $this->decode($store['name']));
		}

		$simple = function($rows, $id, $name) {
			$result = array();

			foreach ($rows as $row) {
				$result[] = array('id' => (int)$row[$id], 'name' => $this->decode($row[$name]));
			}

			return $result;
		};

		return array(
			'endpoint'       => 'index.php?route=catalog/product&user_token=' . $token . '&bp=',
			'edit_url'       => 'index.php?route=catalog/product/edit&user_token=' . $token . '&product_id=',
			'filemanager'    => 'index.php?route=common/filemanager&user_token=' . $token,
			'catalog_url'    => rtrim($catalog, '/'),
			'storefront_url' => $catalog . 'index.php?route=product/product&product_id=',
			'image_base'     => $catalog . 'image/',
			'placeholder'    => $this->model_tool_image->resize('no_image.png', 100, 100),
			'language_id'    => (int)$this->config->get('config_language_id'),
			'languages'      => $languages,
			'stores'         => $stores,
			'customer_groups' => $simple($this->model_customer_customer_group->getCustomerGroups(), 'customer_group_id', 'name'),
			'stock_statuses' => $simple($this->model_localisation_stock_status->getStockStatuses(), 'stock_status_id', 'name'),
			'tax_classes'    => $simple($this->model_localisation_tax_class->getTaxClasses(), 'tax_class_id', 'title'),
			'length_classes' => $simple($this->model_localisation_length_class->getLengthClasses(), 'length_class_id', 'title'),
			'weight_classes' => $simple($this->model_localisation_weight_class->getWeightClasses(), 'weight_class_id', 'title'),
			'layouts'        => $simple($this->model_design_layout->getLayouts(), 'layout_id', 'name'),
			'limit'          => in_array((int)$this->config->get('module_bproduct_limit'), array(5, 10, 25, 50, 100)) ? (int)$this->config->get('module_bproduct_limit') : 10,
			'sort'           => in_array($this->config->get('module_bproduct_sort'), array('pd.name|ASC', 'pd.name|DESC', 'p.date_added|DESC', 'p.date_added|ASC')) ? $this->config->get('module_bproduct_sort') : 'pd.name|ASC',
			'new_status'     => $this->config->has('module_bproduct_new_status') ? (int)$this->config->get('module_bproduct_new_status') : 1,
			'hidden_tabs'    => is_array($this->config->get('module_bproduct_hidden_tabs')) ? array_values($this->config->get('module_bproduct_hidden_tabs')) : array(),
			'tab_order'      => is_array($this->config->get('module_bproduct_tab_order')) ? array_values($this->config->get('module_bproduct_tab_order')) : array(),
			'extra_fields'   => $this->extraKeys(),
			'barcode'        => array(
				'on'     => (int)$this->config->get('module_bproduct_barcode_status'),
				'source' => $this->barcodeSource()
			),
			'can_modify'     => $this->user->hasPermission('modify', 'catalog/product') ? 1 : 0,
			'default_status' => (int)$this->config->get('config_stock_status_id')
		);
	}

	/* --------------------------------------------------------------- helpers */

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
		return $this->isPost() && $this->user->hasPermission('modify', 'catalog/product');
	}

	private function deny() {
		$this->json(array('error' => $this->language->get('error_permission')));
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

	private function keywordTaken($keyword, $store_id, $product_id) {
		$this->load->model('design/seo_url');

		foreach ($this->model_design_seo_url->getSeoUrlsByKeyword($keyword) as $seo_url) {
			if ($seo_url['store_id'] == $store_id && (!$product_id || $seo_url['query'] != 'product_id=' . (int)$product_id)) {
				return true;
			}
		}

		return false;
	}

	/* ------------------------------------------------------------------ list */

	private function formatRows($rows) {
		$ids = array();

		foreach ($rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		$paths = $this->model_catalog_bproduct->getCategoryPaths($ids);
		$slugs = $this->model_catalog_bproduct->getSeoKeywords($ids);
		$images = $this->model_catalog_bproduct->getImageCounts($ids);
		$specials = $this->model_catalog_bproduct->getSpecials($ids, (int)$this->config->get('config_customer_group_id'));
		$currency = $this->config->get('config_currency');

		$result = array();

		foreach ($rows as $row) {
			$id = (int)$row['product_id'];

			$categories = array();

			if (isset($paths[$id])) {
				foreach ($paths[$id] as $path) {
					$categories[] = $this->cleanPath($path);
				}

				sort($categories);
			}

			$result[] = array(
				'product_id'   => $id,
				'name'         => $this->decode($row['name']),
				'model'        => $this->decode($row['model']),
				'sku'          => $this->decode($row['sku']),
				'upc'          => $this->decode($row['upc']),
				'ean'          => $this->decode($row['ean']),
				'jan'          => $this->decode($row['jan']),
				'isbn'         => $this->decode($row['isbn']),
				'mpn'          => $this->decode($row['mpn']),
				'shipping'     => (int)$row['shipping'],
				'status'       => (int)$row['status'],
				'quantity'     => (int)$row['quantity'],
				'price'        => $this->currency->format($row['price'], $currency),
				'special'      => isset($specials[$id]) ? $this->currency->format($specials[$id], $currency) : '',
				'thumb'        => $this->thumb($row['image']),
				'extra_images' => isset($images[$id]) ? $images[$id] : 0,
				'categories'   => $categories,
				'manufacturer' => $this->decode($row['manufacturer']),
				'slug'         => isset($slugs[$id]) ? $this->decode($slugs[$id]) : '',
				'date_added'   => $row['date_added']
			);
		}

		return $result;
	}

	private function ajaxList() {
		$this->load->model('catalog/bproduct');

		$get = $this->request->get;

		$limit = isset($get['limit']) ? (int)$get['limit'] : 10;

		if (!in_array($limit, array(5, 10, 25, 50, 100))) {
			$limit = 10;
		}

		$page = isset($get['page']) ? max(1, (int)$get['page']) : 1;

		$fields = array();

		if (!empty($get['ff'])) {
			$decoded = json_decode(html_entity_decode($get['ff'], ENT_QUOTES, 'UTF-8'), true);

			if (is_array($decoded)) {
				$fields = $decoded;
			}
		}

		$filter = array(
			'filter_q'               => isset($get['q']) ? trim(html_entity_decode($get['q'], ENT_QUOTES, 'UTF-8')) : '',
			'filter_status'          => isset($get['status']) ? $get['status'] : '',
			'filter_category_id'     => isset($get['category_id']) ? (int)$get['category_id'] : 0,
			'filter_sub_category'    => !empty($get['sub_category']),
			'filter_manufacturer_id' => isset($get['manufacturer_id']) ? (int)$get['manufacturer_id'] : 0,
			'filter_model'           => isset($get['model']) ? trim(html_entity_decode($get['model'], ENT_QUOTES, 'UTF-8')) : '',
			'filter_store_id'        => isset($get['store_id']) ? $get['store_id'] : '',
			'filter_fields'          => $fields,
			'sort'                   => isset($get['sort']) ? $get['sort'] : 'pd.name',
			'order'                  => isset($get['order']) ? $get['order'] : 'ASC'
		);

		$total = $this->model_catalog_bproduct->getTotalProducts($filter);

		$pages = max(1, (int)ceil($total / $limit));

		if ($page > $pages) {
			$page = $pages;
		}

		$filter['start'] = ($page - 1) * $limit;
		$filter['limit'] = $limit;

		$rows = $this->model_catalog_bproduct->getProducts($filter);

		$this->json(array(
			'products' => $this->formatRows($rows),
			'total'    => $total,
			'page'     => $page,
			'pages'    => $pages,
			'limit'    => $limit,
			'stats'    => $this->model_catalog_bproduct->getStats()
		));
	}

	private function rowFor($product_id) {
		$rows = $this->model_catalog_bproduct->getProducts(array('filter_ids' => array($product_id)));

		$result = $this->formatRows($rows);

		return $result ? $result[0] : null;
	}

	/* --------------------------------------------------------------- product */

	private function loadFull($product_id, $info) {
		$m = $this->model_catalog_product;

		$data = array();

		foreach ($this->scalarKeys() as $key) {
			$data[$key] = $info[$key];
		}

		$data['manufacturer_id'] = $info['manufacturer_id'];
		$data['points'] = $info['points'];
		$data['image'] = $info['image'];

		$data['product_description'] = $m->getProductDescriptions($product_id);
		$data['product_store'] = $m->getProductStores($product_id);
		$data['product_category'] = $m->getProductCategories($product_id);
		$data['product_filter'] = $m->getProductFilters($product_id);
		$data['product_download'] = $m->getProductDownloads($product_id);
		$data['product_related'] = $m->getProductRelated($product_id);
		$data['product_attribute'] = $m->getProductAttributes($product_id);
		$data['product_option'] = $m->getProductOptions($product_id);
		$data['product_recurring'] = $m->getRecurrings($product_id);
		$data['product_discount'] = $m->getProductDiscounts($product_id);
		$data['product_special'] = $m->getProductSpecials($product_id);
		$data['product_image'] = $m->getProductImages($product_id);
		$data['product_reward'] = $m->getProductRewards($product_id);
		$data['product_seo_url'] = $m->getProductSeoUrls($product_id);
		$data['product_layout'] = $m->getProductLayouts($product_id);

		return $data;
	}

	private function emptyDate($date) {
		return ($date && $date != '0000-00-00') ? $date : '';
	}

	private function ajaxProduct() {
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/filter');
		$this->load->model('catalog/download');
		$this->load->model('catalog/attribute');
		$this->load->model('catalog/option');

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		$info = $this->model_catalog_product->getProduct($product_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bp_error_not_found')));

			return;
		}

		$full = $this->loadFull($product_id, $info);

		$product = array('product_id' => $product_id);

		$fields = array();

		foreach ($this->scalarKeys() as $key) {
			$fields[$key] = $full[$key];
		}

		$fields['date_available'] = $this->emptyDate($fields['date_available']);

		$product['data'] = $this->decode($fields);
		$product['description'] = $this->decode($full['product_description']);

		$categories = array();

		foreach ($full['product_category'] as $category_id) {
			$category = $this->model_catalog_category->getCategory($category_id);

			if ($category) {
				$categories[] = array('id' => (int)$category_id, 'name' => $this->cleanPath($category['path'] ? $category['path'] . ' > ' . $category['name'] : $category['name']));
			}
		}

		$product['categories'] = $categories;

		$filters = array();

		foreach ($full['product_filter'] as $filter_id) {
			$filter = $this->model_catalog_filter->getFilter($filter_id);

			if ($filter) {
				$filters[] = array('id' => (int)$filter_id, 'name' => $this->decode($filter['group'] . ' > ' . $filter['name']));
			}
		}

		$product['filters'] = $filters;

		$downloads = array();

		foreach ($full['product_download'] as $download_id) {
			$download = $this->model_catalog_download->getDownload($download_id);

			if ($download) {
				$downloads[] = array('id' => (int)$download_id, 'name' => $this->decode($download['name']));
			}
		}

		$product['downloads'] = $downloads;

		$related = array();

		foreach ($full['product_related'] as $related_id) {
			$item = $this->model_catalog_product->getProduct($related_id);

			if ($item) {
				$related[] = array('id' => (int)$related_id, 'name' => $this->decode($item['name']), 'model' => $this->decode($item['model']));
			}
		}

		$product['related'] = $related;

		$manufacturer = $this->model_catalog_manufacturer->getManufacturer($full['manufacturer_id']);

		$product['manufacturer'] = $manufacturer ? array('id' => (int)$full['manufacturer_id'], 'name' => $this->decode($manufacturer['name'])) : null;
		$product['stores'] = array_map('intval', $full['product_store']);

		$attributes = array();

		foreach ($full['product_attribute'] as $attribute) {
			$info_attribute = $this->model_catalog_attribute->getAttribute($attribute['attribute_id']);

			if ($info_attribute) {
				$text = array();

				foreach ($attribute['product_attribute_description'] as $language_id => $value) {
					$text[$language_id] = $this->decode($value['text']);
				}

				$attributes[] = array('attribute_id' => (int)$attribute['attribute_id'], 'name' => $this->decode($info_attribute['name']), 'text' => $text);
			}
		}

		$product['attributes'] = $attributes;

		$options = array();

		foreach ($full['product_option'] as $option) {
			$values = array();

			if (in_array($option['type'], array('select', 'radio', 'checkbox', 'image'))) {
				$values = $this->decode($this->model_catalog_option->getOptionValues($option['option_id']));
			}

			$options[] = array(
				'product_option_id'    => (int)$option['product_option_id'],
				'option_id'            => (int)$option['option_id'],
				'name'                 => $this->decode($option['name']),
				'type'                 => $option['type'],
				'required'             => (int)$option['required'],
				'value'                => $this->decode($option['value']),
				'values'               => $values,
				'product_option_value' => $option['product_option_value']
			);
		}

		$product['options'] = $options;

		foreach (array('product_discount' => 'discounts', 'product_special' => 'specials') as $source => $target) {
			$rows = array();

			foreach ($full[$source] as $row) {
				$rows[] = array(
					'customer_group_id' => (int)$row['customer_group_id'],
					'quantity'          => isset($row['quantity']) ? (int)$row['quantity'] : 0,
					'priority'          => (int)$row['priority'],
					'price'             => (float)$row['price'],
					'date_start'        => $this->emptyDate($row['date_start']),
					'date_end'          => $this->emptyDate($row['date_end'])
				);
			}

			$product[$target] = $rows;
		}

		$product['image'] = array('path' => $this->decode($full['image']), 'thumb' => $this->thumb($full['image']));

		$images = array();

		foreach ($full['product_image'] as $image) {
			$images[] = array('path' => $this->decode($image['image']), 'thumb' => $this->thumb($image['image']), 'sort_order' => (int)$image['sort_order']);
		}

		$product['images'] = $images;
		$product['points'] = (int)$full['points'];
		$rewards = array();

		foreach ($full['product_reward'] as $customer_group_id => $reward) {
			$rewards[$customer_group_id] = (int)$reward['points'];
		}

		$product['rewards'] = $rewards ? $rewards : new stdClass();
		$product['seo'] = $full['product_seo_url'] ? $this->decode($full['product_seo_url']) : new stdClass();
		$product['layouts'] = $full['product_layout'] ? $full['product_layout'] : new stdClass();

		$this->json(array('product' => $product));
	}

	/* ------------------------------------------------------------------ save */

	private function mergeSections($data, $sections) {
		foreach ($sections as $name => $section) {
			if (!is_array($section)) {
				continue;
			}

			switch ($name) {
				case 'general':
					if (isset($section['product_description']) && is_array($section['product_description'])) {
						foreach ($section['product_description'] as $language_id => $value) {
							$data['product_description'][(int)$language_id] = array(
								'name'             => isset($value['name']) ? (string)$value['name'] : '',
								'description'      => isset($value['description']) ? (string)$value['description'] : '',
								'meta_title'       => isset($value['meta_title']) ? (string)$value['meta_title'] : '',
								'meta_description' => isset($value['meta_description']) ? (string)$value['meta_description'] : '',
								'meta_keyword'     => isset($value['meta_keyword']) ? (string)$value['meta_keyword'] : '',
								'tag'              => isset($value['tag']) ? (string)$value['tag'] : ''
							);
						}
					}

					break;
				case 'data':
					foreach ($this->scalarKeys() as $key) {
						if (isset($section[$key])) {
							$data[$key] = $section[$key];
						}
					}

					if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['date_available'])) {
						$data['date_available'] = date('Y-m-d');
					}

					break;
				case 'category':
					$data['product_category'] = $this->ids(isset($section['ids']) ? $section['ids'] : array());

					break;
				case 'related':
					$data['product_related'] = $this->ids(isset($section['ids']) ? $section['ids'] : array());

					break;
				case 'links':
					if (isset($section['manufacturer_id'])) {
						$data['manufacturer_id'] = (int)$section['manufacturer_id'];
					}

					if (isset($section['stores'])) {
						$stores = array();

						foreach ((array)$section['stores'] as $store_id) {
							$stores[] = (int)$store_id;
						}

						$data['product_store'] = array_values(array_unique($stores));
					}

					if (isset($section['filters'])) {
						$data['product_filter'] = $this->ids($section['filters']);
					}

					if (isset($section['downloads'])) {
						$data['product_download'] = $this->ids($section['downloads']);
					}

					break;
				case 'attributes':
					$data['product_attribute'] = array();

					foreach ((isset($section['rows']) ? (array)$section['rows'] : array()) as $row) {
						if (empty($row['attribute_id'])) {
							continue;
						}

						$text = array();

						foreach ((isset($row['text']) ? (array)$row['text'] : array()) as $language_id => $value) {
							$text[(int)$language_id] = array('text' => (string)$value);
						}

						$data['product_attribute'][] = array('attribute_id' => (int)$row['attribute_id'], 'product_attribute_description' => $text);
					}

					break;
				case 'options':
					$data['product_option'] = array();

					foreach ((isset($section['rows']) ? (array)$section['rows'] : array()) as $row) {
						if (empty($row['option_id'])) {
							continue;
						}

						$option = array(
							'product_option_id' => isset($row['product_option_id']) ? (int)$row['product_option_id'] : 0,
							'option_id'         => (int)$row['option_id'],
							'name'              => isset($row['name']) ? (string)$row['name'] : '',
							'type'              => isset($row['type']) ? (string)$row['type'] : '',
							'value'             => isset($row['value']) ? (string)$row['value'] : '',
							'required'          => !empty($row['required']) ? 1 : 0
						);

						if (in_array($option['type'], array('select', 'radio', 'checkbox', 'image'))) {
							$option['product_option_value'] = array();

							foreach ((isset($row['product_option_value']) ? (array)$row['product_option_value'] : array()) as $value) {
								$option['product_option_value'][] = array(
									'product_option_value_id' => isset($value['product_option_value_id']) ? (int)$value['product_option_value_id'] : 0,
									'option_value_id'         => (int)$value['option_value_id'],
									'quantity'                => (int)$value['quantity'],
									'subtract'                => !empty($value['subtract']) ? 1 : 0,
									'price'                   => (float)$value['price'],
									'price_prefix'            => $value['price_prefix'] == '-' ? '-' : '+',
									'points'                  => (int)$value['points'],
									'points_prefix'           => $value['points_prefix'] == '-' ? '-' : '+',
									'weight'                  => (float)$value['weight'],
									'weight_prefix'           => $value['weight_prefix'] == '-' ? '-' : '+'
								);
							}
						}

						$data['product_option'][] = $option;
					}

					break;
				case 'discounts':
				case 'specials':
					$key = $name == 'discounts' ? 'product_discount' : 'product_special';

					$data[$key] = array();

					foreach ((isset($section['rows']) ? (array)$section['rows'] : array()) as $row) {
						$item = array(
							'customer_group_id' => (int)$row['customer_group_id'],
							'priority'          => (int)$row['priority'],
							'price'             => (float)$row['price'],
							'date_start'        => $this->validDate(isset($row['date_start']) ? $row['date_start'] : ''),
							'date_end'          => $this->validDate(isset($row['date_end']) ? $row['date_end'] : '')
						);

						if ($name == 'discounts') {
							$item['quantity'] = (int)$row['quantity'];
						}

						$data[$key][] = $item;
					}

					break;
				case 'images':
					if (isset($section['image'])) {
						$data['image'] = (string)$section['image'];
					}

					$data['product_image'] = array();

					foreach ((isset($section['rows']) ? (array)$section['rows'] : array()) as $row) {
						if (!empty($row['image'])) {
							$data['product_image'][] = array('image' => (string)$row['image'], 'sort_order' => (int)$row['sort_order']);
						}
					}

					break;
				case 'reward':
					if (isset($section['points'])) {
						$data['points'] = (int)$section['points'];
					}

					if (isset($section['rewards']) && is_array($section['rewards'])) {
						$data['product_reward'] = array();

						foreach ($section['rewards'] as $customer_group_id => $points) {
							$data['product_reward'][(int)$customer_group_id] = array('points' => (int)$points);
						}
					}

					break;
				case 'seo':
					foreach ((isset($section['keywords']) ? (array)$section['keywords'] : array()) as $store_id => $languages) {
						foreach ((array)$languages as $language_id => $keyword) {
							$data['product_seo_url'][(int)$store_id][(int)$language_id] = trim((string)$keyword);
						}
					}

					break;
				case 'design':
					foreach ((isset($section['layouts']) ? (array)$section['layouts'] : array()) as $store_id => $layout_id) {
						$data['product_layout'][(int)$store_id] = (int)$layout_id;
					}

					break;
			}
		}

		return $data;
	}

	private function validDate($date) {
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? $date : '0000-00-00';
	}

	// Mirrors the checks of catalog/product::validateForm() for the sections that were sent.
	private function validate($data, $sections, $product_id) {
		$error = array();

		if (isset($sections['general'])) {
			foreach ($sections['general']['product_description'] as $language_id => $value) {
				$value = $data['product_description'][(int)$language_id];

				if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
					$error['name'][$language_id] = $this->language->get('error_name');
				}

				if ((utf8_strlen($value['meta_title']) < 1) || (utf8_strlen($value['meta_title']) > 255)) {
					$error['meta_title'][$language_id] = $this->language->get('error_meta_title');
				}
			}
		}

		if (isset($sections['data'])) {
			if ((utf8_strlen($data['model']) < 1) || (utf8_strlen($data['model']) > 64)) {
				$error['model'] = $this->language->get('error_model');
			}

			$source = $this->barcodeSource();
			$current = $this->model_catalog_product->getProduct($product_id);

			if (!isset($error[$source]) && (!$current || $this->encode($this->decode($current[$source])) !== $data[$source])) {
				$message = $this->barcodeError($data[$source], $product_id);

				if ($message) {
					$error[$source] = $message;
				}
			}
		}

		if (isset($sections['seo']) && !empty($sections['seo']['keywords'])) {
			foreach ($sections['seo']['keywords'] as $store_id => $languages) {
				foreach ((array)$languages as $language_id => $keyword) {
					$keyword = $data['product_seo_url'][(int)$store_id][(int)$language_id];

					if ($keyword === '') {
						continue;
					}

					if (count(array_keys($data['product_seo_url'][(int)$store_id], $keyword)) > 1) {
						$error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
					} elseif ($this->keywordTaken($keyword, (int)$store_id, $product_id)) {
						$error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');
					}
				}
			}
		}

		return $error;
	}

	private function ajaxSave() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/bproduct');

		$input = $this->input();

		$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
		$info = $this->model_catalog_product->getProduct($product_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bp_error_not_found')));

			return;
		}

		$sections = isset($input['sections']) && is_array($input['sections']) ? $this->encode($input['sections']) : array();

		if (isset($sections['general']) && !isset($sections['general']['product_description'])) {
			unset($sections['general']);
		}

		$data = $this->mergeSections($this->loadFull($product_id, $info), $sections);

		$error = $this->validate($data, $sections, $product_id);

		if ($error) {
			$error['warning'] = $this->language->get('error_warning');

			$this->json(array('error' => $error));

			return;
		}

		$this->model_catalog_product->editProduct($product_id, $data);

		$this->json(array('success' => $this->language->get('bp_text_saved'), 'row' => $this->rowFor($product_id), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	/* ------------------------------------------------------------------- add */

	private function defaultKeyword($name, $code, $used) {
		$base = $this->slugify($name);

		if ($base === '') {
			return '';
		}

		$keyword = $base;
		$n = 2;

		while (in_array($keyword, $used) || $this->keywordTaken($keyword, 0, 0)) {
			$keyword = $base . '-' . $n++;
		}

		return $keyword;
	}

	private function ajaxAdd() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/bproduct');
		$this->load->model('localisation/language');

		$input = $this->encode($this->input());

		$name = isset($input['name']) ? trim($input['name']) : '';
		$model = isset($input['model']) ? trim($input['model']) : '';

		$error = array();

		if ((utf8_strlen($name) < 1) || (utf8_strlen($name) > 255)) {
			$error['name'] = $this->language->get('error_name');
		}

		if ((utf8_strlen($model) < 1) || (utf8_strlen($model) > 64)) {
			$error['model'] = $this->language->get('error_model');
		}

		if (!isset($error['model']) && $this->barcodeSource() == 'model' && ($message = $this->barcodeError($model, 0))) {
			$error['model'] = $message;
		}

		if ($error) {
			$error['warning'] = $this->language->get('error_warning');

			$this->json(array('error' => $error));

			return;
		}

		$data = array(
			'model'           => $model,
			'sku'             => '',
			'upc'             => '',
			'ean'             => '',
			'jan'             => '',
			'isbn'            => '',
			'mpn'             => '',
			'location'        => '',
			'quantity'        => isset($input['quantity']) && $input['quantity'] !== '' ? max(0, (int)$input['quantity']) : 1,
			'minimum'         => 1,
			'subtract'        => 1,
			'stock_status_id' => (int)$this->config->get('config_stock_status_id'),
			'date_available'  => date('Y-m-d'),
			'manufacturer_id' => isset($input['manufacturer_id']) ? (int)$input['manufacturer_id'] : 0,
			'shipping'        => 1,
			'price'           => isset($input['price']) ? max(0, (float)$input['price']) : 0,
			'points'          => 0,
			'weight'          => 0,
			'weight_class_id' => (int)$this->config->get('config_weight_class_id'),
			'length'          => 0,
			'width'           => 0,
			'height'          => 0,
			'length_class_id' => (int)$this->config->get('config_length_class_id'),
			'status'          => !empty($input['status']) ? 1 : 0,
			'tax_class_id'    => 0,
			'sort_order'      => 1,
			'image'           => isset($input['image']) ? (string)$input['image'] : '',
			'product_store'   => array(0),
			'product_category' => $this->ids(isset($input['categories']) ? $input['categories'] : array()),
			'product_description' => array(),
			'product_seo_url' => array()
		);

		foreach ($this->extraKeys() as $key) {
			$data[$key] = '';
		}

		$used = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if (!$language['status']) {
				continue;
			}

			$data['product_description'][$language['language_id']] = array(
				'name'             => $name,
				'description'      => '',
				'meta_title'       => $name,
				'meta_description' => '',
				'meta_keyword'     => '',
				'tag'              => ''
			);

			$keyword = $this->defaultKeyword($name, $language['code'], $used);

			if ($keyword !== '') {
				$used[] = $keyword;
				$data['product_seo_url'][0][$language['language_id']] = $keyword;
			}
		}

		$product_id = $this->model_catalog_product->addProduct($data);

		$this->json(array('success' => $this->language->get('bp_text_added'), 'product_id' => (int)$product_id, 'row' => $this->rowFor($product_id), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	/* ---------------------------------------------------- delete, copy, bulk */

	private function ajaxDelete() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/bproduct');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());

		foreach ($ids as $product_id) {
			$this->model_catalog_product->deleteProduct($product_id);
		}

		$this->json(array('success' => sprintf($this->language->get('bp_text_deleted'), count($ids)), 'count' => count($ids), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	// A clone must not share model/SKU with its source: the SKU is cleared and the model gets a -copy suffix
	private function uniqueCopy($product_id) {
		$row = $this->model_catalog_bproduct->getModelSku($product_id);

		if (!$row) {
			return;
		}

		$base = $this->decode($row['model']);
		$n = 1;

		do {
			$suffix = '-copy' . ($n > 1 ? $n : '');
			$model = $this->encode(utf8_substr($base, 0, 64 - utf8_strlen($suffix)) . $suffix);
			$n++;
		} while ($this->model_catalog_bproduct->isFieldTaken('model', $model, $product_id));

		$this->model_catalog_bproduct->editModelSku($product_id, $model, '');
	}

	private function ajaxCopy() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/bproduct');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());

		foreach ($ids as $product_id) {
			$this->model_catalog_product->copyProduct($product_id);

			if ($this->config->get('module_bproduct_barcode_status')) {
				$this->uniqueCopy($this->model_catalog_bproduct->getLastProductId());
			}
		}

		$this->json(array('success' => sprintf($this->language->get('bp_text_copied'), count($ids)), 'count' => count($ids), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	private function ajaxStatus() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/bproduct');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());
		$status = !empty($input['status']) ? 1 : 0;

		$this->model_catalog_bproduct->editStatus($ids, $status);

		$this->json(array('success' => sprintf($this->language->get($status ? 'bp_text_enabled' : 'bp_text_disabled'), count($ids)), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	private function ajaxBulk() {
		if (!$this->canModify()) {
			$this->deny();

			return;
		}

		$this->load->model('catalog/bproduct');

		$input = $this->input();
		$ids = $this->ids(isset($input['ids']) ? $input['ids'] : array());
		$value = isset($input['value']) ? (int)$input['value'] : 0;

		if (isset($input['action']) && $input['action'] == 'category' && $value) {
			$this->model_catalog_bproduct->addToCategory($ids, $value);
		} elseif (isset($input['action']) && $input['action'] == 'manufacturer') {
			$this->model_catalog_bproduct->editManufacturer($ids, $value);
		} else {
			$this->json(array('error' => $this->language->get('bp_error_invalid')));

			return;
		}

		$this->json(array('success' => sprintf($this->language->get('bp_text_updated'), count($ids)), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	/* ---------------------------------------------------------- autocomplete */

	private function ajaxAc() {
		$type = isset($this->request->get['type']) ? $this->request->get['type'] : '';
		$q = isset($this->request->get['q']) ? trim($this->request->get['q']) : '';
		$limit = 15;
		$items = array();

		switch ($type) {
			case 'category':
				$this->load->model('catalog/category');

				foreach ($this->model_catalog_category->getCategories(array('filter_name' => $q, 'sort' => 'name', 'start' => 0, 'limit' => $limit)) as $row) {
					$items[] = array('id' => (int)$row['category_id'], 'name' => $this->cleanPath($row['name']));
				}

				break;
			case 'manufacturer':
				$this->load->model('catalog/manufacturer');

				foreach ($this->model_catalog_manufacturer->getManufacturers(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
					$items[] = array('id' => (int)$row['manufacturer_id'], 'name' => $this->decode($row['name']));
				}

				break;
			case 'product':
				$this->load->model('catalog/bproduct');

				$exclude = isset($this->request->get['exclude']) ? (int)$this->request->get['exclude'] : 0;

				foreach ($this->model_catalog_bproduct->getProducts(array('filter_q' => html_entity_decode($q, ENT_QUOTES, 'UTF-8'), 'start' => 0, 'limit' => $limit + 1)) as $row) {
					if ((int)$row['product_id'] != $exclude && count($items) < $limit) {
						$items[] = array('id' => (int)$row['product_id'], 'name' => $this->decode($row['name']), 'sub' => $this->decode($row['model']));
					}
				}

				break;
			case 'filter':
				$this->load->model('catalog/filter');

				foreach ($this->model_catalog_filter->getFilters(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
					$items[] = array('id' => (int)$row['filter_id'], 'name' => $this->decode($row['group'] . ' > ' . $row['name']));
				}

				break;
			case 'download':
				$this->load->model('catalog/download');

				foreach ($this->model_catalog_download->getDownloads(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
					$items[] = array('id' => (int)$row['download_id'], 'name' => $this->decode($row['name']));
				}

				break;
			case 'attribute':
				$this->load->model('catalog/attribute');

				foreach ($this->model_catalog_attribute->getAttributes(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
					$items[] = array('id' => (int)$row['attribute_id'], 'name' => $this->decode($row['name']), 'sub' => $this->decode($row['attribute_group']));
				}

				break;
			case 'option':
				$this->load->model('catalog/option');

				foreach ($this->model_catalog_option->getOptions(array('filter_name' => $q, 'start' => 0, 'limit' => $limit)) as $row) {
					$values = array();

					if (in_array($row['type'], array('select', 'radio', 'checkbox', 'image'))) {
						$values = $this->decode($this->model_catalog_option->getOptionValues($row['option_id']));
					}

					$items[] = array('id' => (int)$row['option_id'], 'name' => $this->decode($row['name']), 'type' => $row['type'], 'values' => $values);
				}

				break;
		}

		$this->json(array('items' => $items));
	}

	private function ajaxSeo() {
		$this->load->model('design/seo_url');

		$keyword = isset($this->request->get['keyword']) ? trim(html_entity_decode($this->request->get['keyword'], ENT_QUOTES, 'UTF-8')) : '';
		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		$json = array('ok' => 1);

		if ($keyword !== '' && $this->keywordTaken($this->encodeKeyword($keyword), $store_id, $product_id)) {
			$json['ok'] = 0;

			$base = $this->slugify($keyword);
			$n = 2;
			$suggest = $base . '-' . $n;

			while ($this->keywordTaken($suggest, $store_id, $product_id)) {
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

		$this->load->model('catalog/product');
		$this->load->model('catalog/bproduct');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');

		$input = $this->input();

		$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
		$info = $this->model_catalog_product->getProduct($product_id);

		if (!$info) {
			$this->json(array('error' => $this->language->get('bp_error_not_found')));

			return;
		}

		$data = $this->loadFull($product_id, $info);

		$stores = array(0);

		foreach ($this->model_setting_store->getStores() as $store) {
			$stores[] = (int)$store['store_id'];
		}

		$names = array();

		foreach ($data['product_description'] as $description) {
			if (trim($description['name']) !== '') {
				$names[] = $description['name'];
			}
		}

		$generated = 0;

		foreach ($stores as $store_id) {
			$used = array();

			foreach ($this->model_localisation_language->getLanguages() as $language) {
				if (!$language['status']) {
					continue;
				}

				$language_id = (int)$language['language_id'];

				if (!empty($data['product_seo_url'][$store_id][$language_id])) {
					$used[] = $data['product_seo_url'][$store_id][$language_id];

					continue;
				}

				$name = isset($data['product_description'][$language_id]) ? $data['product_description'][$language_id]['name'] : '';
				$keyword = '';

				foreach (array_merge(array($name), $names) as $candidate) {
					$base = $this->slugify($candidate);

					if ($base !== '') {
						break;
					}
				}

				if ($base === '') {
					continue;
				}

				$keyword = $base;
				$n = 2;

				while (in_array($keyword, $used) || $this->keywordTaken($keyword, $store_id, $product_id)) {
					$keyword = $base . '-' . $n++;
				}

				$used[] = $keyword;
				$data['product_seo_url'][$store_id][$language_id] = $keyword;
				$generated++;
			}
		}

		if (!$generated) {
			$this->json(array('error' => $this->language->get('bp_slug_empty')));

			return;
		}

		$this->model_catalog_product->editProduct($product_id, $data);

		$this->json(array('success' => 1, 'row' => $this->rowFor($product_id), 'stats' => $this->model_catalog_bproduct->getStats()));
	}

	private function encodeKeyword($keyword) {
		return htmlspecialchars($keyword, ENT_COMPAT, 'UTF-8');
	}
}
