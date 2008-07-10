<?php
$where = where();
$fields = fields($_GET["edit"]);
foreach ($fields as $name => $field) {
	if (isset($_GET["default"]) ? $field["auto_increment"] || preg_match('~text|blob~', $field["type"]) : !isset($field["privileges"][$where ? "update" : "insert"])) {
		unset($fields[$name]);
	}
}
if ($_POST && !$error) {
	$set = array();
	if (isset($_POST["delete"])) {
		$set = true;
		$query = "DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
		$message = lang('Item has been deleted.');
	} else {
		foreach ($fields as $name => $field) {
			$val = process_input($name, $field);
			if ($val !== false) {
				if (!isset($_GET["default"])) {
					$set[] = idf_escape($name) . " = $val";
				} elseif ($field["type"] == "timestamp") {
					$set[] = " MODIFY " . idf_escape($name) . " timestamp" . ($field["null"] ? " NULL" : "") . " DEFAULT $val" . ($_POST["on_update"][bracket_escape($name)] ? " ON UPDATE CURRENT_TIMESTAMP" : "");
				} else {
					$set[] = " ALTER " . idf_escape($name) . ($val == ($field["null"] ? "NULL" : "''") ? " DROP DEFAULT" : " SET DEFAULT $val");
				}
			}
		}
		if (isset($_GET["default"])) {
			$query = "ALTER TABLE " . idf_escape($_GET["edit"]) . implode(",", $set);
			$message = lang('Default values has been set.');
		} elseif ($where) {
			$query = "UPDATE " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
			$message = lang('Item has been updated.');
		} else {
			$query = "INSERT INTO " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set);
			$message = lang('Item has been inserted.');
		}
	}
	if (!$set || $mysql->query($query)) {
		redirect($SELF . (isset($_GET["default"]) ? "table=" : ($_POST["insert"] ? "edit=" : "select=")) . urlencode($_GET["edit"]), ($set ? $message : null));
	}
	$error = $mysql->error;
}
page_header((isset($_GET["default"]) ? lang('Default values') : ($_GET["where"] ? lang('Edit') : lang('Insert'))), $error, array((isset($_GET["default"]) ? "table" : "select") => $_GET["edit"]), $_GET["edit"]);

unset($row);
if ($_POST) {
	$row = (array) $_POST["fields"];
	foreach ((array) $_POST["null"] as $key => $val) {
		$row[$key] = null;
	}
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"]) && !preg_match('~binary|blob~', $field["type"])) {
			$select[] = ($field["type"] == "enum" || $field["type"] == "set" ? "1*" . idf_escape($name) . " AS " : "") . idf_escape($name);
		}
	}
	if ($select) {
		$result = $mysql->query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1");
		$row = $result->fetch_assoc();
	} else {
		$row = array();
	}
}
?>

<form action="" method="post" enctype="multipart/form-data">
<?php
if ($fields) {
	unset($create);
	echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
	foreach ($fields as $name => $field) {
		echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
		if (!isset($row)) {
			$value = $field["default"];
		} elseif (strlen($row[$name]) && ($field["type"] == "enum" || $field["type"] == "set")) {
			$value = intval($row[$name]);
		} else {
			$value = $row[$name];
		}
		input($name, $field, $value);
		if (isset($_GET["default"]) && $field["type"] == "timestamp") {
			$id = htmlspecialchars("on_update-$name");
			if (!isset($create) && !$_POST) {
				//! disable sql_mode NO_FIELD_OPTIONS
				$create = $mysql->result($mysql->query("SHOW CREATE TABLE " . idf_escape($_GET["edit"])), 1);
			}
			$checked = ($_POST ? $_POST["on_update"][bracket_escape($name)] : preg_match("~\n\\s*" . preg_quote(idf_escape($name), '~') . " timestamp.* on update CURRENT_TIMESTAMP~i", $create));
			echo '<label for="' . $id . '"><input type="checkbox" name="on_update[' . htmlspecialchars(bracket_escape($name)) . ']" id="' . $id . '" value="1"' . ($checked ? ' checked="checked"' : '') . ' />' . lang('ON UPDATE CURRENT_TIMESTAMP') . '</label>';
		}
		echo "</td></tr>\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($fields) { ?>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (!isset($_GET["default"])) { ?><input type="submit" name="insert" value="<?php echo lang('Save and insert next'); ?>" /><?php } ?>
<?php } ?>
<?php if ($where) { ?> <input type="submit" name="delete" value="<?php echo lang('Delete'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
