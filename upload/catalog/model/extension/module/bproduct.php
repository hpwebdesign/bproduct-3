<?php
/**
 * Quick Category Manager (bproduct) - storefront Display Name lookup.
 * Used by the OCMOD in install.xml to replace the category name shown to customers.
 */
class ModelExtensionModuleBproduct extends Model {
	private $names = null;

	public function getDisplayName($category_id) {
		if ($this->names === null) {
			$this->names = array();

			if ($this->config->get('module_bproduct_status') && $this->config->get('module_bproduct_category_status') && $this->config->get('module_bproduct_category_display')) {
				$table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "bproduct_category_display'");

				if ($table->num_rows) {
					$query = $this->db->query("SELECT category_id, display_name FROM `" . DB_PREFIX . "bproduct_category_display` WHERE language_id = '" . (int)$this->config->get('config_language_id') . "' AND display_name <> ''");

					foreach ($query->rows as $row) {
						$this->names[(int)$row['category_id']] = $row['display_name'];
					}
				}
			}
		}

		return isset($this->names[(int)$category_id]) ? $this->names[(int)$category_id] : '';
	}
}
