<?php

	$MYSQL_HOST = "localhost";
	$MYSQL_USER = "pepr";
	$MYSQL_PASSWORD = "PeprPassword123!@#";

function get_distance(float $lat1, float $lon1, float $lat2, float $lon2) : float {
	// Haversine formula for distance between points
    $lat_dist = ($lat2 - $lat1) * M_PI / 180.0; 
    $lon_dist = ($lon2 - $lon1) * M_PI / 180.0; 
  
    // convert to radians 
    $lat1 = ($lat1) * M_PI / 180.0; 
    $lat2 = ($lat2) * M_PI / 180.0; 
  
    $a = pow(sin($lat_dist / 2), 2) +  pow(sin($lon_dist / 2), 2) *  cos($lat1) * cos($lat2); 

    // radius of the Earth in miles
    $rad = 3959; 
    $c = 2 * asin(sqrt($a)); 
    return $rad * $c; 
}


function make_database() : bool {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	
	try {
		$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
    	$sql ="CREATE TABLE `activities` (
			`ID` int NOT NULL AUTO_INCREMENT,
			`activity_name` varchar(50) NOT NULL,
			`activity_type` enum('run','bike','climb','elliptical','sup','swim','kayak','yoga','weight','hike','workout') NOT NULL,
			`activity_start` datetime NOT NULL,
			`activity_duration` mediumint NOT NULL,
			`activity_distance` float DEFAULT NULL,
			`activity_elevation_gain` mediumint DEFAULT NULL,
			`activity_pace_per_mile` varchar(2000) DEFAULT NULL,
			`activity_description` varchar(2000) DEFAULT NULL,
			`activity_gpx` mediumtext,
			`shoe` int DEFAULT NULL,
			PRIMARY KEY (`ID`)
		  );" ;
		 $db_connection -> exec($sql);
		 $sql = "CREATE TABLE `shoes` (
			`id` int NOT NULL AUTO_INCREMENT,
			`model` varchar(45) DEFAULT NULL,
			`brand` varchar(45) DEFAULT NULL,
			`nickname` varchar(45) DEFAULT NULL,
			`mileage` decimal(12,9) DEFAULT NULL,
			`is_active` int DEFAULT NULL,
			PRIMARY KEY (`id`)
		  );";
		$db_connection -> exec($sql);
		$sql = "CREATE TABLE `miles` (
			`idpace` int NOT NULL AUTO_INCREMENT,
			`mile_count` int NOT NULL,
			`activity_id` int NOT NULL,
			`mile_pace` int NOT NULL,
			`mile_elevation` int DEFAULT NULL,
			`start_lat` decimal(10,9) DEFAULT NULL,
			`start_lon` decimal(10,9) DEFAULT NULL,
			`end_lat` decimal(10,9) DEFAULT NULL,
			`end_lon` decimal(10,9) DEFAULT NULL,
			`actual_distance` decimal(10,9) DEFAULT NULL,
			PRIMARY KEY (`idpace`)
		  );";
			$db_connection -> exec($sql);
		$sql = "CREATE TABLE `personal_records` (
			`id` int NOT NULL AUTO_INCREMENT,
			`distance` varchar(45) DEFAULT NULL,
			`time` int DEFAULT NULL,
			`activity_id` int DEFAULT NULL,
			PRIMARY KEY (`id`)
		  );";
		$db_connection -> exec($sql);
	}
	catch (PDOException $e) {
    	echo "Error: " . $e->getMessage() . "<br>";
    	die();
	}

	header("Location: ./index.html?init");
}

function get_json_points(int $activity_id) {
	$result = [];

	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);

	$activity_gpx = $db_connection->query("SELECT activity_gpx FROM activities WHERE ID = " . intval($activity_id))->fetch();
	$xml_obj = simplexml_load_string($activity_gpx[0]);
	if (!$xml_obj) {
		return $xml_obj;
	}
	$xml_obj -> registerXPathNameSpace("gpx", "http://www.topografix.com/GPX/1/1");

	foreach ($xml_obj -> xpath("//gpx:trk/gpx:trkseg/gpx:trkpt") as $cur_point) {
		array_push($result, [["elevation" => $cur_point -> ele], 
							["time" => $cur_point -> time], 
							["lat" => $cur_point["lat"]], 
							["lon" => $cur_point["lon"]]]);
	}

	return $result;

}

function add_activity($act_name, $act_type, $act_start, $act_duration, $act_distance, $act_elevation, $act_pace, $act_description, $act_shoes, $act_gpx, $splits) : bool {
	$sql = "INSERT INTO activities (activity_name, activity_type, activity_start, activity_duration, activity_distance, activity_elevation_gain, activity_pace_per_mile, activity_description, shoe, activity_gpx)
			VALUES ('". addslashes($act_name) . "', '". $act_type . "', '" . $act_start . "', " . $act_duration . ", " . round($act_distance, 2) . ", " . $act_elevation . ", '". $act_pace . "', '" . addslashes($act_description) ."', " . $act_shoes . ", '" . $act_gpx . "')";

	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);

	$db_connection -> exec($sql);
	$activity_id = $db_connection -> lastInsertId();

	foreach ($splits as $split) {
		$sql = "INSERT INTO miles (activity_id, mile_count, mile_pace, actual_distance) 
				VALUES (". $activity_id . ", " . $split[0] . ", " . $split[1] . ", " . $split[2] . ");";
		$db_connection -> exec($sql);
	}
	
	if (floatval($act_distance) > 0 && ($act_type == "run" || $act_type == "hike")) {
		add_miles_to_shoe(intval($act_shoes), floatval($act_distance));
	}

	return true;
}

function get_activities(int $number_of_activities, int $record_offset) {

	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;

	$result = [];
	$sql = "SELECT ID, activity_name, activity_type, activity_distance, activity_duration, activity_start, activity_description FROM activities ORDER BY activity_start DESC LIMIT " . strval($number_of_activities) . " OFFSET " . strval($record_offset);
	
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
    error_log($sql);
	foreach ($db_connection->query($sql) as $row) {
		array_push($result, ["id" => $row["ID"], 
							"name" => $row["activity_name"],
							"type" => $row["activity_type"],
							"distance" => $row["activity_distance"],
							"duration" => $row["activity_duration"],
							"start" => $row["activity_start"],
							"description" => nl2br($row["activity_description"])]);
	}

	header('Content-Type: application/json');
	echo json_encode($result);;
}

function get_activities_by_length(float $min, float $max, string $type, string $start_date = "1900-01-01 00:01:01") {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;

	$result = [];
	$sql = "SELECT ID, activity_name, activity_distance, activity_duration, activity_start, activity_pace_per_mile FROM activities WHERE activity_type = '" . $type . "' AND activity_start >= '" . $start_date . "' AND activity_distance BETWEEN " . $min . " AND " . $max . " ORDER BY activity_start";
	
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);

	foreach ($db_connection->query($sql) as $row) {
		array_push($result, ["id" => $row["ID"], 
							"name" => $row["activity_name"],
							"type" => $row["activity_type"],
							"distance" => $row["activity_distance"],
							"duration" => $row["activity_duration"],
							"start" => $row["activity_start"],
							"pace" => $row["activity_pace_per_mile"], 
							"description" => $row["activity_description"]]);
	}

	header('Content-Type: application/json');
	echo json_encode($result);;
}

function get_activity(int $activity_id) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);

	$activity_contents = $db_connection->query("SELECT ID, activity_name, activity_type, activity_distance, activity_duration, activity_start, activity_pace_per_mile, activity_description, activity_elevation_gain, shoe FROM activities WHERE ID = " . intval($activity_id))->fetch();
	$activity_contents['activity_description'] = str_replace("\n", "\n<br>", $activity_contents['activity_description']);
	
	$shoe = $db_connection->query("SELECT model, brand, nickname FROM shoes WHERE id = ". $activity_contents['shoe']) -> fetch();
	$shoe = $shoe['brand'] . " " . $shoe['model'] . " (" . $shoe['nickname'] .")";
	$activity_contents['shoe'] = $shoe;

	$no_distance = array("climb", "yoga", "weight", "workout");
	if (in_array($activity_contents["activity_type"], $no_distance)) {
		header('Content-Type: application/json');
		echo json_encode($activity_contents);
	}
	else {
		array_push($activity_contents, ["points" => get_json_points($activity_id)]);
		header('Content-Type: application/json');
		echo json_encode($activity_contents);
	}
}

function get_splits(int $activity_id) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);

	$activity_contents = $db_connection->query("SELECT mile_count, mile_pace FROM miles WHERE activity_id = " . intval($activity_id)) -> fetchAll();
	header('Content-Type: application/json');
	echo json_encode($activity_contents);
}

function get_stats_from_file(string $activity_name, string $activity_type, string $activity_description, string $activity_shoes, string $fstring) {
	$xml_obj = simplexml_load_string($fstring);
	$total_distance = 0.0;
	$total_time_in_seconds = 0;
	$total_elevation = 0.0;
	$pace_in_seconds = 0;
	$last_point = NULL;
	$last_elevation = NULL;
	$last_time = NULL;
	$splits = [];
	$lap_distance = 0.0;
	$lap_time = 0;
	$lap_count = 1;
	$time_interval = 0;
	$overage = 0.0;
	$xml_obj -> registerXPathNameSpace("gpx", "http://www.topografix.com/GPX/1/1");
	$activity_date = str_replace("Z", "", str_replace("T", " ", $xml_obj -> metadata -> time));

	foreach ($xml_obj -> xpath("//gpx:trk/gpx:trkseg/gpx:trkpt") as $cur_point) {
		$cur_point -> registerXPathNameSpace("gpx", "http://www.topografix.com/GPX/1/1");
		if (is_null($last_point)) {
			$last_point = $cur_point;
			print_r($cur_point);
			$last_time = str_replace("Z", "", str_replace("T", " ", $cur_point -> time));
			$last_elevation = array("ele" => floatval($cur_point -> ele), "lat" => floatval($cur_point["lat"]), "lon" => floatval($cur_point["lon"]));
		}
		else {
			$cur_distance = get_distance(floatval($last_point["lat"]), floatval($last_point["lon"]), floatval($cur_point["lat"]), floatval($cur_point["lon"]));
			$total_distance += $cur_distance;
			$lap_distance += $cur_distance;

			$cur_time = str_replace("Z", "", str_replace("T", " ", $cur_point -> time));
			$time_interval = intval(date_diff(date_create($last_time), date_create($cur_time)) ->format("%s"));
			$total_time_in_seconds += $time_interval;
			$lap_time += $time_interval;

			if ($lap_distance >= 1) {
				$overage = $lap_distance - 1.00;
				$overage_time = $time_interval/$cur_distance * $overage;
				array_push($splits, [$lap_count, $lap_time - $overage_time, $lap_distance - $overage]);
				$lap_distance = $overage;
				$lap_count ++;
				$lap_time = $overage_time;
			}

			
			if (get_distance(floatval($cur_point -> lat), floatval($cur_point -> lon), floatval($last_elevation["lat"]), floatval($last_elevation["lon"])) > 6) {
				$total_elevation += floatval($cur_point -> ele) - $last_elevation["ele"];
				$last_elevation = array("ele" => floatval($cur_point -> ele), "lat" => floatval($cur_point["lat"]), "lon" => floatval($cur_point["lon"]));
			}
			
			$last_point = $cur_point;
			$last_time = $cur_time;
		}
	}

	array_push($splits, [$lap_count, $lap_time, $lap_distance]);
	add_activity($activity_name, $activity_type, $activity_date, $total_time_in_seconds, $total_distance, $total_elevation, $total_time_in_seconds / $total_distance, $activity_description, $activity_shoes, str_replace("'", "''", $fstring), $splits);
	header("Location: index.html");
}

function get_stats_from_manual($activity_name, $activity_type, $activity_description, $activity_shoes, $activity_date, $activity_distance, $activity_hours, $activity_minutes, $activity_seconds)
{
	if (!isset($activity_distance)) { $activity_distance = -1; $activity_elevation = -1;}
	$activity_duration = -1;
	$activity_duration += intval($activity_hours) * 3600;
	$activity_duration += intval($activity_minutes) * 60;
	$activity_duration += intval($activity_seconds);
	$activity_elevation = -1;
	$no_distance = array("climb", "yoga", "weight", "workout");
	if (in_array($activity_type, $no_distance) || $activity_distance == 0) {
		$activity_distance = -1;
	}
	add_activity($activity_name, $activity_type, $activity_date, $activity_duration, $activity_distance, $activity_elevation, $activity_duration / $activity_distance, $activity_description, $activity_shoes, "NOGPX", []);
	header("Location: index.html");
}

function get_total_miles(string $activity_type) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$total_dist = 0.00;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$distances = $db_connection->query("SELECT activity_distance FROM activities WHERE activity_type = '" . $activity_type . "'");
	foreach ($distances as $dist) {
		$total_dist += floatval($dist[0]);
	}
	echo $total_dist;
}

function get_miles_between_dates(string $start, string $end, string $activity_type) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$results = array();
	$activities = "(activity_type = '";
	if ($activity_type == "*") {
		$activities .= "bike' OR activity_type = 'run' OR activity_type = 'elliptical' OR activity_type = 'hike' OR activity_type = 'kayak' OR activity_type = 'swim' OR activity_type='sup')"; 
	}
	else {
		$activities .= $activity_type . "')";
	}
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$sql = "SELECT activity_distance, activity_start, activity_type, activity_duration, ID FROM activities WHERE " . 
			$activities . " AND activity_start >= '" . $start . "' AND activity_start <= '" . $end . "';";

	$distances = $db_connection->query($sql);
	foreach ($distances as $dist) {
		array_push($results, [$dist[0], $dist[1], $dist[2], $dist[3], $dist[4]]);
	}

	header('Content-Type: application/json');
	echo json_encode($results);
}

function delete_activity(int $activity_id) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$sql_delete = "DELETE FROM activities WHERE id=" . $activity_id . ";";
	// Subtract the activity's distance from the mileage on the shoe used
	// - Get the distance and shoe for the activity
	$sql_shoe_mileage = "SELECT activity_distance, shoe FROM activities WHERE id=" . $activity_id .";";
	$sql_shoe_mileage = $db_connection -> query($sql_shoe_mileage) -> fetch(PDO::FETCH_ASSOC);
	$subtracted_mileage = floatval($sql_shoe_mileage["activity_distance"]);
	// - Get the mileage on the shoe
	$sql_old_mileage = "SELECT mileage FROM shoes WHERE id=" . $sql_shoe_mileage["shoe"] . ";";
	$sql_old_mileage = $db_connection -> query($sql_old_mileage) -> fetch(PDO::FETCH_ASSOC);
	// - Subtract activity's mileage from the shoe mileage and update the record
	$new_mileage = floatval($sql_old_mileage["mileage"]) - $subtracted_mileage;
	$sql_shoe_mileage = $db_connection -> query("UPDATE shoes SET mileage = " . $new_mileage . " WHERE id = ". $sql_shoe_mileage["shoe"] . ";");
	// Actually delete the activity
	$db_connection -> query($sql_delete);
	header("Location: index.html");
}

function add_shoe($brand, $model, $nickname, $miles, $active) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$sql = "INSERT INTO pepr_db.shoes (brand, model, nickname, mileage, is_active) VALUES ('" . $brand ."', '" . $model . "', '" . $nickname . "', " . $miles . ", 1);";
	$db_connection -> exec($sql);
	header("Location: index.html");
}

function delete_shoe(int $shoe_id) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	// Shoe doesn't actually get deleted, just set to inactive
	$sql = "UPDATE shoes SET is_active = 0 WHERE id=" . $shoe_id . ";";
	$db_connection -> query($sql);
	header("Location: index.html");
}

function add_miles_to_shoe($id, $miles) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$total = $db_connection->query("SELECT mileage FROM shoes WHERE id=" . $id) -> fetch();
	echo $total;
	if (!$total) {
		return;
	}
	$total["mileage"] += floatval($miles);
	$db_connection->exec("UPDATE shoes SET mileage = " . $total["mileage"] . " WHERE (id = '". $id ."');");
}

function list_shoes($active) {
	global $MYSQL_HOST, $MYSQL_USER, $MYSQL_PASSWORD;
	$db_connection = new PDO("mysql:host=".$MYSQL_HOST.";dbname=pepr_db", $MYSQL_USER, $MYSQL_PASSWORD);
	$results = array();
	$sql = "SELECT nickname, brand, model, id, mileage FROM shoes WHERE is_active = " . $active;
	$shoes = $db_connection->query($sql);

	foreach ($shoes as $shoe) {

		array_push($results, ["name" => $shoe[0], "brand" => $shoe[1], "model" => $shoe[2], "id" => $shoe[3], "miles" => $shoe[4]]);
	}

	header('Content-Type: application/json');
	echo json_encode($results);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	switch ($_GET['f']) {
		case 'get_distance':
			echo get_distance($_GET["lat1"], $_GET["lon1"], $_GET["lat2"], $_GET["lon2"]);
			break;
		case 'get_stats_from_file':
			break;
		case 'make_database':
			make_database();
			break;
		case 'get_activities':
			get_activities(intval($_GET['n']), intval($_GET['o']));
			break;
		case 'get_activities_by_length':
			if (isset($_GET['start'])) {
				get_activities_by_length(floatval($_GET['min']), floatval($_GET['max']), $_GET['type'], $_GET['start']);
			}
			else{
				get_activities_by_length(floatval($_GET['min']), floatval($_GET['max']), $_GET['type']);
			}
			break;
		case 'get_activity':
			get_activity(intval($_GET['id']));
			break;
		case 'get_json_points':
			get_json_points(intval($_GET['id']));
			break;
		case 'get_total_miles':
			get_total_miles($_GET['type']);
			break;
		case 'get_miles_by_year':
			get_miles_by_year($_GET['year'], $_GET['type']);
			break;
		case 'get_miles_between_dates':
			get_miles_between_dates($_GET['start'], $_GET['end'], $_GET['type']);
			break;
		case 'delete_activity':
			delete_activity(intval($_GET['id']));
			break;
		case 'get_splits':
			get_splits(intval($_GET['id']));
			break;
		case 'add_shoe':
			add_shoe($_GET['brand'], $_GET['model'], $_GET['nickname'], $_GET['miles'], $_GET['active']);
			break;
		case 'delete_shoe':
			delete_shoe($_GET['id']);
			break;
		case 'add_miles_to_shoe':
			add_miles_to_shoe($_GET['id'], $_GET['miles']);
			break;
		case 'list_shoes':
			list_shoes($_GET['active']);
		}

}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if(isset($_FILES['gpx'])) {
		if ($_FILES['gpx']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['gpx']['tmp_name'])) { //checks that file is uploaded
			$shoe = -1;
			if (isset($_POST['shoes'])) {
				$shoe = $_POST['shoes'];
			}
  			get_stats_from_file($_POST['activity_name'], $_POST['activity_type'], strval($_POST['activity_description']), $shoe, file_get_contents($_FILES['gpx']['tmp_name'])); 
			
		}
	}
	else {	// if no file is uploaded, save as a manual activity
		if (isset($_POST['shoes'])) {
			$shoe = $_POST['shoes'];
		}
		else {
			$shoe = -1;
		}
		get_stats_from_manual(strval($_POST['activity_name']), $_POST['activity_type'], strval($_POST['activity_description']),  $shoe,
							$_POST['activity_date'] . " " . $_POST['activity_time'] . ":00", $_POST['activity_distance'], $_POST['activity_hours'], $_POST['activity_minutes'], $_POST['activity_seconds']);
	}
}




?>
