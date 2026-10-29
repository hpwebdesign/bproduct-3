<?php
/**
 * Quick Product Manager (bproduct) - module settings.
 * The OCMOD hook only hands the product list over to catalog/bproduct while the status is enabled.
 */
class ControllerExtensionModuleBproduct extends Controller {
	private $error = array();

	private $v_d = '';
	private $version = '1.0.0.0';
	private $extension_code = 'bproduct';
	private $domain = '';
	private $extension_type = 'com';

	private $sorts = array('pd.name|ASC', 'pd.name|DESC', 'p.date_added|DESC', 'p.date_added|ASC');
	private $limits = array(5, 10, 25, 50, 100);

	private $barcode_sources = array('model', 'sku');

	// Default tab order of the product editor
	private $all_tabs = array('general', 'data', 'category', 'related', 'links', 'attributes', 'options', 'discounts', 'specials', 'images', 'reward', 'seo', 'design');

	// Drawer tabs that can be hidden (general, data, category, seo and images are always shown)
	private $optional_tabs = array('related', 'links', 'attributes', 'options', 'discounts', 'specials', 'reward', 'design');

	public function index() {
		$this->load->language('extension/module/bproduct');

		$this->domain = str_replace('www.', '', $_SERVER['SERVER_NAME']);
		$this->houseKeeping();
		$this->rightman();

		if ($this->domain != $this->v_d) {
			return $this->storeAuth();
		}

		$this->document->setTitle($this->language->get('heading_title2'));

		$this->load->model('setting/setting');

		$data['version'] = $this->version;
		$data['extension_code'] = $this->extension_code;
		$data['doc_link'] = 'https://hpwebdesign.' . $this->extension_type . '/docs/' . $this->extension_code;
		$data['ticket_link'] = 'https://hpwebdesign.' . $this->extension_type . '/support';

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/bproduct', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/bproduct/save', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['manager'] = $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'], true);

		$defaults = array('status' => 0, 'limit' => 10, 'sort' => 'pd.name|ASC', 'new_status' => 1, 'barcode_status' => 0, 'barcode_source' => 'model');

		foreach ($defaults as $key => $default) {
			if (isset($this->request->post['module_bproduct_' . $key])) {
				$data['module_bproduct_' . $key] = $this->request->post['module_bproduct_' . $key];
			} elseif ($this->config->has('module_bproduct_' . $key)) {
				$data['module_bproduct_' . $key] = $this->config->get('module_bproduct_' . $key);
			} else {
				$data['module_bproduct_' . $key] = $default;
			}
		}

		$data['limits'] = $this->limits;

		$data['barcode_sources'] = array();

		foreach ($this->barcode_sources as $source) {
			$data['barcode_sources'][] = array('value' => $source, 'text' => $this->language->get('entry_' . $source));
		}


		$hidden = $this->config->get('module_bproduct_hidden_tabs');
		$hidden = is_array($hidden) ? $hidden : array();

		$order = $this->config->get('module_bproduct_tab_order');
		$order = array_merge(is_array($order) ? array_values(array_intersect($order, $this->all_tabs)) : array(), array());
		$order = array_merge($order, array_diff($this->all_tabs, $order));

		$data['tabs'] = array();

		foreach ($order as $tab) {
			$data['tabs'][] = array(
				'key'      => $tab,
				'text'     => $this->language->get('text_tab_' . $tab),
				'required' => !in_array($tab, $this->optional_tabs),
				'shown'    => !in_array($tab, $hidden)
			);
		}

		$data['sorts'] = array();

		foreach ($this->sorts as $sort) {
			$data['sorts'][] = array('value' => $sort, 'text' => $this->language->get('text_sort_' . str_replace(array('.', '|'), '_', $sort)));
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bproduct', $data));
	}

	public function save() {
		$this->load->language('extension/module/bproduct');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error']['warning'] = $this->language->get('error_permission');
		} elseif (!$this->validate()) {
			$json['error'] = $this->error;
		} else {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_bproduct', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->model('setting/setting');

		// Disabled until the store owner turns it on
		$this->model_setting_setting->editSetting('module_bproduct', array(
			'module_bproduct_status'     => 0,
			'module_bproduct_limit'      => 10,
			'module_bproduct_sort'       => 'pd.name|ASC',
			'module_bproduct_new_status' => 1,
			'module_bproduct_barcode_status'    => 0,
			'module_bproduct_barcode_source'    => 'model',
			'module_bproduct_hidden_tabs' => array(),
			'module_bproduct_tab_order'   => $this->all_tabs
		));
	}

	public function uninstall() {
		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('module_bproduct');
	}

	// Store validation screen (license missing / domain mismatch); validation.twig is delivered by bariklabs houseKeeping()
	private function storeAuth() {
		$data['curl_status'] = in_array('curl', get_loaded_extensions());
		$data['extension_code'] = $this->extension_code;
		$data['extension_type'] = $this->extension_type;
		$data['user_token'] = $this->session->data['user_token'];
		$this->flushdata();

		$this->document->setTitle($this->language->get('text_validation'));

		$data['text_curl'] = $this->language->get('text_curl');
		$data['text_disabled_curl'] = $this->language->get('text_disabled_curl');
		$data['text_validation'] = $this->language->get('text_validation');
		$data['text_validate_store'] = $this->language->get('text_validate_store');
		$data['text_information_provide'] = $this->language->get('text_information_provide');
		$data['domain_name'] = $this->domain;

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title2'),
			'href' => $this->url->link('extension/module/bproduct', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		if (!is_file(DIR_TEMPLATE . 'extension/module/validation.twig')) {
			// validate.zip could not be downloaded (no internet / write permission)
			$data['error_warning'] = $this->language->get('error_no_internet_access');
			$this->response->setOutput($data['header'] . '<div id="content"><div class="container-fluid"><div class="alert alert-danger" style="margin-top:20px">' . $data['error_warning'] . '</div></div></div>' . $data['footer']);
			return;
		}

		$this->response->setOutput($this->load->view('extension/module/validation', $data));
	}

	private function getBariklabs() {
		require_once(DIR_APPLICATION . 'controller/common/bariklabs.php');

		return new ControllerCommonBariklabs($this->registry);
	}

	private function rightman() {
		$result = $this->getBariklabs()->rightman($this->extension_code, $this->domain);

		if ($result === 'api_down') {
			// License server unreachable: only forgive stores that already hold a license key, otherwise validation is required
			if ($this->hasLicenseKey()) {
				$this->error['warning'] = $this->language->get('error_no_internet_access');
				$this->v_d = $this->domain;
			}
		} elseif ($result === true) {
			$this->v_d = $this->domain;
		}
	}

	private function hasLicenseKey() {
		$query = $this->db->query("SHOW TABLES LIKE 'hpwd_license'");

		if (!$query->num_rows) {
			return false;
		}

		$query = $this->db->query("SELECT `license_key` FROM `hpwd_license` WHERE `code` = '" . $this->db->escape($this->extension_code) . "' LIMIT 1");

		return $query->num_rows && $query->row['license_key'] != '';
	}

	private function houseKeeping() {
		$this->getBariklabs()->houseKeeping();
	}

	// An unvalidated store must not keep the module enabled (the OCMOD hook checks this setting)
	public function flushdata() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bproduct_status'");
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bproduct')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = &$this->request->post;

		$post['module_bproduct_status'] = !empty($post['module_bproduct_status']) ? 1 : 0;
		$post['module_bproduct_new_status'] = !empty($post['module_bproduct_new_status']) ? 1 : 0;
		$post['module_bproduct_barcode_status'] = !empty($post['module_bproduct_barcode_status']) ? 1 : 0;

		if (!isset($post['module_bproduct_barcode_source']) || !in_array($post['module_bproduct_barcode_source'], $this->barcode_sources)) {
			$post['module_bproduct_barcode_source'] = 'model';
		}

		$shown = isset($post['module_bproduct_tabs']) && is_array($post['module_bproduct_tabs']) ? array_keys($post['module_bproduct_tabs']) : array();
		$post['module_bproduct_hidden_tabs'] = array_values(array_diff($this->optional_tabs, $shown));
		unset($post['module_bproduct_tabs']);

		$order = isset($post['module_bproduct_tab_order']) && is_array($post['module_bproduct_tab_order']) ? array_values(array_intersect($post['module_bproduct_tab_order'], $this->all_tabs)) : array();
		$order = array_unique($order);
		$post['module_bproduct_tab_order'] = array_merge($order, array_diff($this->all_tabs, $order));

		if (!isset($post['module_bproduct_limit']) || !in_array((int)$post['module_bproduct_limit'], $this->limits)) {
			$post['module_bproduct_limit'] = 10;
		}

		if (!isset($post['module_bproduct_sort']) || !in_array($post['module_bproduct_sort'], $this->sorts)) {
			$post['module_bproduct_sort'] = 'pd.name|ASC';
		}

		return !$this->error;
	}
}
