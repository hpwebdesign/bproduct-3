<?php
/**
 * Extra queries for the Quick Product Manager (bproduct).
 * Anything OpenCart already ships in catalog/product (add, edit, delete, copy, getters)
 * is reused from the default model and is NOT repeated here.
 */
class ModelCatalogBproduct extends Model {
	private $identifiers = array('sku', 'upc', 'ean', 'jan', 'isbn', 'mpn');

	private function like($value) {
		return str_replace(array('%', '_'), array('\%', '\_'), $this->db->escape($value));
	}

	private function buildWhere($data) {
		$sql = " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_ids'])) {
			$sql .= " AND p.product_id IN (" . implode(',', array_map('intval', $data['filter_ids'])) . ")";
		}

		if (!empty($data['filter_q'])) {
			$q = $this->like($data['filter_q']);

			$sql .= " AND (pd.name LIKE '%" . $q . "%' OR p.model LIKE '%" . $q . "%')";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND p.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND p.product_id IN (SELECT p2c.product_id FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.category_id IN (SELECT cp.category_id FROM " . DB_PREFIX . "category_path cp WHERE cp.path_id = '" . (int)$data['filter_category_id'] . "'))";
			} else {
				$sql .= " AND p.product_id IN (SELECT p2c.product_id FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.category_id = '" . (int)$data['filter_category_id'] . "')";
			}
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		if (!empty($data['filter_model'])) {
			$sql .= " AND p.model LIKE '%" . $this->like($data['filter_model']) . "%'";
		}

		if (isset($data['filter_store_id']) && $data['filter_store_id'] !== '') {
			$sql .= " AND p.product_id IN (SELECT p2s.product_id FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.store_id = '" . (int)$data['filter_store_id'] . "')";
		}

		if (!empty($data['filter_fields']) && is_array($data['filter_fields'])) {
			foreach ($data['filter_fields'] as $field) {
				if (!isset($field['k']) || !isset($field['v']) || $field['v'] === '') {
					continue;
				}

				if ($field['k'] == 'shipping') {
					$sql .= " AND p.shipping = '" . (int)$field['v'] . "'";
				} elseif (in_array($field['k'], $this->identifiers)) {
					if (!empty($field['x'])) {
						$sql .= " AND p." . $field['k'] . " = '" . $this->db->escape($field['v']) . "'";
					} else {
						$sql .= " AND p." . $field['k'] . " LIKE '%" . $this->like($field['v']) . "%'";
					}
				}
			}
		}

		return $sql;
	}

	public function getProducts($data = array()) {
		$sql = "SELECT p.product_id, pd.name, p.model, p.sku, p.upc, p.ean, p.jan, p.isbn, p.mpn, p.shipping, p.status, p.image, p.price, p.quantity, p.date_added, p.manufacturer_id, m.name AS manufacturer FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)";

		$sql .= $this->buildWhere($data);

		$order = (isset($data['order']) && $data['order'] == 'DESC') ? 'DESC' : 'ASC';

		if (isset($data['sort']) && in_array($data['sort'], array('p.status', 'p.price', 'p.quantity'))) {
			$sql .= " ORDER BY " . $data['sort'] . " " . $order . ", pd.name ASC, p.product_id ASC";
		} elseif (isset($data['sort']) && $data['sort'] == 'p.date_added') {
			$sql .= " ORDER BY p.date_added " . $order . ", pd.name ASC, p.product_id ASC";
		} else {
			$sql .= " ORDER BY pd.name " . $order . ", p.product_id ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			$start = max(0, (int)$data['start']);
			$limit = max(1, (int)$data['limit']);

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)";

		$sql .= $this->buildWhere($data);

		return (int)$this->db->query($sql)->row['total'];
	}

	public function getStats() {
		$query = $this->db->query("SELECT COUNT(*) AS total, COALESCE(SUM(p.status = 1), 0) AS enabled FROM " . DB_PREFIX . "product p");

		$attention = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product p WHERE NOT EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.product_id = p.product_id) OR NOT EXISTS (SELECT 1 FROM " . DB_PREFIX . "seo_url su WHERE su.query = CONCAT('product_id=', p.product_id))");

		return array(
			'total'     => (int)$query->row['total'],
			'enabled'   => (int)$query->row['enabled'],
			'attention' => (int)$attention->row['total']
		);
	}

	// Full category path ("Parent > Child") for each category of the given products.
	public function getCategoryPaths($product_ids) {
		$data = array();

		if (!$product_ids) {
			return $data;
		}

		$ids = implode(',', array_map('intval', $product_ids));

		$query = $this->db->query("SELECT p2c.product_id, p2c.category_id, GROUP_CONCAT(cd.name ORDER BY cp.level SEPARATOR ' > ') AS path FROM " . DB_PREFIX . "product_to_category p2c LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd ON (cp.path_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE p2c.product_id IN (" . $ids . ") GROUP BY p2c.product_id, p2c.category_id");

		foreach ($query->rows as $row) {
			if ($row['path'] !== null && $row['path'] !== '') {
				$data[$row['product_id']][] = $row['path'];
			}
		}

		return $data;
	}

	// SEO keyword of the default store in the admin language, keyed by product id.
	public function getSeoKeywords($product_ids) {
		$data = array();

		if (!$product_ids) {
			return $data;
		}

		$queries = array();

		foreach ($product_ids as $product_id) {
			$queries[] = "'product_id=" . (int)$product_id . "'";
		}

		$query = $this->db->query("SELECT query, keyword FROM " . DB_PREFIX . "seo_url WHERE store_id = '0' AND language_id = '" . (int)$this->config->get('config_language_id') . "' AND query IN (" . implode(',', $queries) . ")");

		foreach ($query->rows as $row) {
			$data[(int)substr($row['query'], 11)] = $row['keyword'];
		}

		return $data;
	}

	public function getSpecials($product_ids, $customer_group_id) {
		$data = array();

		if (!$product_ids) {
			return $data;
		}

		$ids = array_map('intval', $product_ids);

		$query = $this->db->query("SELECT product_id, price FROM " . DB_PREFIX . "product_special WHERE product_id IN (" . implode(',', $ids) . ") AND customer_group_id = '" . (int)$customer_group_id . "' AND (date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW()) ORDER BY priority ASC, price ASC");

		foreach ($query->rows as $row) {
			if (!isset($data[(int)$row['product_id']])) {
				$data[(int)$row['product_id']] = (float)$row['price'];
			}
		}

		return $data;
	}

	public function getImageCounts($product_ids) {
		$data = array();

		if (!$product_ids) {
			return $data;
		}

		$query = $this->db->query("SELECT product_id, COUNT(*) AS total FROM " . DB_PREFIX . "product_image WHERE product_id IN (" . implode(',', array_map('intval', $product_ids)) . ") GROUP BY product_id");

		foreach ($query->rows as $row) {
			$data[$row['product_id']] = (int)$row['total'];
		}

		return $data;
	}

	public function editStatus($product_ids, $status) {
		if ($product_ids) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET status = '" . (int)$status . "', date_modified = NOW() WHERE product_id IN (" . implode(',', array_map('intval', $product_ids)) . ")");

			$this->cache->delete('product');
		}
	}

	public function addToCategory($product_ids, $category_id) {
		foreach ($product_ids as $product_id) {
			$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
		}

		$this->cache->delete('product');
	}

	public function editManufacturer($product_ids, $manufacturer_id) {
		if ($product_ids) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET manufacturer_id = '" . (int)$manufacturer_id . "', date_modified = NOW() WHERE product_id IN (" . implode(',', array_map('intval', $product_ids)) . ")");

			$this->cache->delete('product');
		}
	}

	// $field must come from a whitelist; it is a column name, not user input
	public function isFieldTaken($field, $value, $product_id) {
		$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE `" . $field . "` = '" . $this->db->escape($value) . "' AND product_id <> '" . (int)$product_id . "' LIMIT 1");

		return (bool)$query->num_rows;
	}

	public function getLastProductId() {
		$query = $this->db->query("SELECT MAX(product_id) AS product_id FROM " . DB_PREFIX . "product");

		return (int)$query->row['product_id'];
	}

	public function getModelSku($product_id) {
		$query = $this->db->query("SELECT model, sku FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");

		return $query->row;
	}

	public function editModelSku($product_id, $model, $sku) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET model = '" . $this->db->escape($model) . "', sku = '" . $this->db->escape($sku) . "', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");

		$this->cache->delete('product');
	}
}
