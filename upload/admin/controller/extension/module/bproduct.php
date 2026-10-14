<?php
/**
 * Quick Product Manager (bproduct) - module settings.
 * The OCMOD hook only hands the product list over to catalog/bproduct while the status is enabled.
 */
class ControllerExtensionModuleBproduct extends Controller {
	private $error = array();

	private $sorts = array('pd.name|ASC', 'pd.name|DESC', 'p.date_added|DESC', 'p.date_added|ASC');
	private $limits = array(5, 10, 25, 50, 100);

	// Default tab order of the product editor
	private $all_tabs = array('general', 'data', 'category', 'related', 'links', 'attributes', 'options', 'discounts', 'specials', 'images', 'reward', 'seo', 'design');

	// Drawer tabs that can be hidden (general, data, category, seo and images are always shown)
	private $optional_tabs = array('related', 'links', 'attributes', 'options', 'discounts', 'specials', 'reward', 'design');

	public function index() {
		$this->load->language('extension/module/bproduct');

		$this->document->setTitle($this->language->get('heading_title2'));

		$this->load->model('setting/setting');

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

		$defaults = array('status' => 0, 'limit' => 10, 'sort' => 'pd.name|ASC', 'new_status' => 1);

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
			'module_bproduct_hidden_tabs' => array(),
			'module_bproduct_tab_order'   => $this->all_tabs
		));
	}

	public function uninstall() {
		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('module_bproduct');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bproduct')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$post = &$this->request->post;

		$post['module_bproduct_status'] = !empty($post['module_bproduct_status']) ? 1 : 0;
		$post['module_bproduct_new_status'] = !empty($post['module_bproduct_new_status']) ? 1 : 0;

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
