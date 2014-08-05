<?php

class DB_Utilities
{
	function create_insert($db, $table_name, $data_array)
	{
		$column_names = array();
		$question_marks = array();

		foreach(array_keys($data_array) as $col_name)
		{
			$column_names[] = "`$col_name`";
			$question_marks[] = '?';
		}

		$sql_insert = "INSERT IGNORE INTO $table_name(".implode(',', $column_names).') VALUES ('.implode(',', $question_marks).')';
		return $db->prepare($sql_insert);
	}

	function create_select($db, $table_name, $data_array)
	{
		$retrieve_column_name = $data_array[0];
		$match_column_name = $data_array[1];

		$sql_select = "SELECT $retrieve_column_name FROM $table_name WHERE $match_column_name = ?";

		return $db->prepare($sql_select); 
	}
	
	function rss_date_to_mysql_date($rss_date)
	{
		$time_from_epoch = strtotime($rss_date);
		return date('Y-m-d H:i:s', $time_from_epoch);
	}
}
