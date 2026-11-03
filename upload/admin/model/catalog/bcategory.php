<?php
/**
 * Extra queries for the Quick Category Manager (bproduct).
 * Add, edit and delete go through the default catalog/category model; this model only adds
 * list queries, bulk helpers and the Display Name storage.
 */
class ModelCatalogBcategory extends Model {
	private $table_ready = false;

	private function like($value) {
		return str_replace(array('%', '_'), array('\%', '\_'), $this->db->escape($value));
	}

	public function ensureTable() {
		if (!$this->table_ready) {
			$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "bproduct_category_display` (
				`category_id` INT(11) NOT NULL,
				`language_id` INT(11) NOT NULL,
				`display_name` VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (`category_id`, `language_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");

			$this->table_ready = true;
		}
	}

	private function buildWhere($data) {
		$sql = '';

		if (!empty($data['filter_ids'])) {
			$sql .= " AND cp.category_id IN (" . implode(',', array_map('intval', $data['filter_ids'])) . ")";
		}

		if (!empty($data['filter_q'])) {
			$sql .= " AND cd2.name LIKE '%" . $this->like($data['filter_q']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND c1.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_parent_id'])) {
			$sql .= " AND cp.category_id IN (SELECT category_id FROM " . DB_PREFIX . "category_path WHERE path_id = '" . (int)$data['filter_parent_id'] . "')";
		}

		return $sql;
	}

	public function getCategories($data = array()) {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' &gt; ') AS name, c1.parent_id, c1.sort_order, c1.status, c1.top, MAX(cp.level) AS level FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '" . $language_id . "' AND cd2.language_id = '" . $language_id . "'";

		$sql .= $this->buildWhere($data);
		$sql .= " GROUP BY cp.category_id";

		$sorts = array('name' => 'name', 'sort_order' => 'c1.sort_order', 'status' => 'c1.status');
		$sort = isset($data['sort']) && isset($sorts[$data['sort']]) ? $sorts[$data['sort']] : 'name';
		$order = isset($data['order']) && $data['order'] == 'DESC' ? 'DESC' : 'ASC';

		$sql .= " ORDER BY " . $sort . " " . $order . ($sort != 'name' ? ", name ASC" : "");

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . max(0, (int)$data['start']) . "," . max(1, (int)$data['limit']);
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalCategories($data = array()) {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT COUNT(DISTINCT cp.category_id) AS total FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category c1 ON (cp.category_id = c1.category_id) LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd2.language_id = '" . $language_id . "'";

		$sql .= $this->buildWhere($data);

		return (int)$this->db->query($sql)->row['total'];
	}

	public function getStats() {
		$row = $this->db->query("SELECT COUNT(*) AS total, SUM(status = 1) AS enabled, SUM(parent_id = 0) AS top FROM " . DB_PREFIX . "category")->row;
		$empty = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c WHERE NOT EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_category p2c WHERE p2c.category_id = c.category_id)")->row;

		return array(
			'total'   => (int)$row['total'],
			'enabled' => (int)$row['enabled'],
			'top'     => (int)$row['top'],
			'empty'   => (int)$empty['total']
		);
	}

	public function getProductCounts($category_ids) {
		$data = array();

		if (!$category_ids) {
			return $data;
		}

		$query = $this->db->query("SELECT category_id, COUNT(DISTINCT product_id) AS total FROM " . DB_PREFIX . "product_to_category WHERE category_id IN (" . implode(',', array_map('intval', $category_ids)) . ") GROUP BY category_id");

		foreach ($query->rows as $row) {
			$data[(int)$row['category_id']] = (int)$row['total'];
		}

		return $data;
	}

	public function getSeoKeywords($category_ids) {
		$data = array();

		if (!$category_ids) {
			return $data;
		}

		$queries = array();

		foreach ($category_ids as $category_id) {
			$queries[] = "'category_id=" . (int)$category_id . "'";
		}

		$query = $this->db->query("SELECT query, keyword FROM " . DB_PREFIX . "seo_url WHERE store_id = '0' AND language_id = '" . (int)$this->config->get('config_language_id') . "' AND query IN (" . implode(',', $queries) . ")");

		foreach ($query->rows as $row) {
			$data[(int)substr($row['query'], 12)] = $row['keyword'];
		}

		return $data;
	}

	public function getDisplayNames($category_ids) {
		$data = array();

		if (!$category_ids) {
			return $data;
		}

		$this->ensureTable();

		$query = $this->db->query("SELECT category_id, display_name FROM `" . DB_PREFIX . "bproduct_category_display` WHERE language_id = '" . (int)$this->config->get('config_language_id') . "' AND display_name <> '' AND category_id IN (" . implode(',', array_map('intval', $category_ids)) . ")");

		foreach ($query->rows as $row) {
			$data[(int)$row['category_id']] = $row['display_name'];
		}

		return $data;
	}

	public function getDisplayNamesByCategory($category_id) {
		$data = array();

		$this->ensureTable();

		$query = $this->db->query("SELECT language_id, display_name FROM `" . DB_PREFIX . "bproduct_category_display` WHERE category_id = '" . (int)$category_id . "'");

		foreach ($query->rows as $row) {
			$data[(int)$row['language_id']] = $row['display_name'];
		}

		return $data;
	}

	// $names: language_id => already escaped text; an empty text removes the display name of that language
	public function saveDisplayNames($category_id, $names) {
		$this->ensureTable();

		foreach ($names as $language_id => $name) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "bproduct_category_display` WHERE category_id = '" . (int)$category_id . "' AND language_id = '" . (int)$language_id . "'");

			if ($name !== '') {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "bproduct_category_display` SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "', display_name = '" . $this->db->escape($name) . "'");
			}
		}
	}

	public function deleteDisplayNames($category_ids) {
		if ($category_ids) {
			$this->ensureTable();

			$this->db->query("DELETE FROM `" . DB_PREFIX . "bproduct_category_display` WHERE category_id IN (" . implode(',', array_map('intval', $category_ids)) . ")");
		}
	}

	public function getSubtreeIds($category_id) {
		$ids = array();

		foreach ($this->db->query("SELECT category_id FROM " . DB_PREFIX . "category_path WHERE path_id = '" . (int)$category_id . "'")->rows as $row) {
			$ids[] = (int)$row['category_id'];
		}

		return $ids;
	}

	// True when $parent_id is $category_id itself or one of its descendants
	public function isInSubtree($parent_id, $category_id) {
		if ((int)$parent_id == (int)$category_id) {
			return true;
		}

		return (bool)$this->db->query("SELECT 1 FROM " . DB_PREFIX . "category_path WHERE category_id = '" . (int)$parent_id . "' AND path_id = '" . (int)$category_id . "' LIMIT 1")->num_rows;
	}

	public function editStatus($category_ids, $status) {
		if ($category_ids) {
			$this->db->query("UPDATE " . DB_PREFIX . "category SET status = '" . (int)$status . "', date_modified = NOW() WHERE category_id IN (" . implode(',', array_map('intval', $category_ids)) . ")");

			$this->cache->delete('category');
		}
	}

	public function editTop($category_ids, $top) {
		if ($category_ids) {
			$this->db->query("UPDATE " . DB_PREFIX . "category SET top = '" . (int)$top . "', date_modified = NOW() WHERE category_id IN (" . implode(',', array_map('intval', $category_ids)) . ")");

			$this->cache->delete('category');
		}
	}

	public function editParent($category_id, $parent_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "category SET parent_id = '" . (int)$parent_id . "', date_modified = NOW() WHERE category_id = '" . (int)$category_id . "'");
	}
}
