<?php
$config = json_decode(file_get_contents('../server.json'), true);

$game_path = "${config['q3a_path']}${config['mod']}/";
$curl_user_agent_string = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';
$rcon_command = 'reload_fs';
$db_path = '../records.db';

$rcon_password = trim(file_get_contents($config['rcon_password_file_path']));
$output = array();

# Get the name of the file to be downloaded, and whether it is a pk3 or a bsp
$client_filename = isset($_GET['name']) ? $_GET['name'] : '';
$client_file_extension = isset($_GET['ext']) ? $_GET['ext'] : '';

# Make sure the given extension is valid, then construct the URL at which the file can be found
if (!array_key_exists($client_file_extension, $config['map_source_url_formats'])) {
	set_error($output, "Invalid file extension given: `$client_file_extension'.");
	finish($output);
}
$map_source_url_format = $config['map_source_url_formats'][$client_file_extension];

# Encode the filename and create the URL
$map_source_url = sprintf($map_source_url_format, urlencode($client_filename));

# Check if the file exists at the source URL
$map_source_head_curl = curl_init($map_source_url);
curl_setopt($map_source_head_curl, CURLOPT_USERAGENT, $curl_user_agent_string);
curl_setopt($map_source_head_curl, CURLOPT_HEADER, true);
curl_setopt($map_source_head_curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($map_source_head_curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($map_source_head_curl, CURLOPT_NOBODY, true);
$curl_response = curl_exec($map_source_head_curl);
$content_disposition_filename = get_content_disposition_filename($curl_response);
$map_source_head_curl_http_code = curl_getinfo($map_source_head_curl, CURLINFO_HTTP_CODE);
curl_close($map_source_head_curl);
if ($map_source_head_curl_http_code !== 200) {
	set_error($output, "Server responded with code $map_source_head_curl_http_code for URL `$map_source_url'.");
	finish($output);
}
if ($content_disposition_filename === null) {
	set_error($output, "Server did not give filename for URL `$map_source_url'.");
	finish($output);
}

# Sanitize the retrieved filename
if (strpos($content_disposition_filename, '/') > -1) {
	set_error($output, "Invalid character `/' in filename `$content_disposition_filename'.");
	finish($output);
}

# Establish a connection to the pk3 name database
$database = new SQLite3($db_path);
if (!$database) {
	set_error($output, "Failed to fetch list of downloaded pk3s.");
	finish($output);
}
$statement = $database->prepare('SELECT name FROM pack WHERE name=:name');
if (!$statement) {
	set_error($output, "Failed to fetch list of downloaded pk3s.");
	finish($output);
}

# Check if the pk3 is already in the pk3 name database
$pk3_name = basename($content_disposition_filename, '.pk3');
$statement->bindValue(':name', $pk3_name);
$result = $statement->execute();
$pk3_exists = !!$result->fetchArray();
$result->finalize();
$statement->close();
if ($pk3_exists) {
	set_error($output, "`$content_disposition_filename' already downloaded.");
	finish($output);
}

# Download the pk3
$pk3_destination_path = tempnam(sys_get_temp_dir(), $content_disposition_filename);
$map_destination_file = fopen($pk3_destination_path, 'wb');
if ($map_destination_file === false) {
	set_error($output, 'Failed to open local file for download.');
	finish($output);
}
$map_source_curl = curl_init($map_source_url);
curl_setopt($map_source_curl, CURLOPT_USERAGENT, $curl_user_agent_string);
curl_setopt($map_source_curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($map_source_curl, CURLOPT_HEADER, false);
curl_setopt($map_source_curl, CURLOPT_FILE, $map_destination_file);
curl_exec($map_source_curl);
curl_close($map_source_curl);
fclose($map_destination_file);

# Open the pk3 (it is a zip file)
$pk3_zip = new ZipArchive;
if ($pk3_zip->open($pk3_destination_path) === false) {
	set_error($output, "Failed to open archive `$content_disposition_filename'");
	finish($output);
}

# Unzip maps/* from the pk3 into Q3's maps/ and delete the original pk3
$pk3_zip_bsp_paths = array();
for ($i = 0; $i < $pk3_zip->numFiles; ++$i) {
	$pk3_zip_path = $pk3_zip->getNameIndex($i);
	if (preg_match('/^maps\/[^\/]+$/', $pk3_zip_path)) {
		$pk3_zip_bsp_paths[] = $pk3_zip_path;
	}
}
$pk3_zip_extraction_result = $pk3_zip->extractTo($game_path, $pk3_zip_bsp_paths);
$pk3_zip->close();
if (file_exists($pk3_destination_path)) {
	unlink ($pk3_destination_path);
}
if ($pk3_zip_extraction_result === false) {
	set_error($output, 'Error extracting archive.');
	finish($output);
}
foreach ($pk3_zip_bsp_paths as $pk3_zip_bsp_path) {
	rename("$game_path$pk3_zip_bsp_path", "$game_path" . strtolower($pk3_zip_bsp_path));
}

# Record the downloaded pk3's name in the pk3 name database
$statement = $database->prepare('INSERT INTO pack (name) VALUES(:name)');
if (!$statement) {
	set_error($output, "Failed to update list of downloaded pk3s.");
	finish($output);
}
$statement->bindValue(':name', $pk3_name);
$result = $statement->execute();
$result->finalize();
$statement->close();

# Reload the Q3 filesystem
$rcon_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$rcon_datagram = "\xff\xff\xff\xffrcon \"$rcon_password\" $rcon_command"; 
socket_sendto($rcon_socket, $rcon_datagram, strlen($rcon_datagram), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']);
socket_close($rcon_socket);

# Get the filenames of the extracted bsps
$bsp_file_names = array_map(function($file_path) {
	return basename($file_path);
}, $pk3_zip_bsp_paths);

$output['success'] = "Successfully downloaded `$map_source_url'! Reloading filesystem.";
$output['filename'] = $content_disposition_filename;
$output['contents'] = $bsp_file_names;
finish($output);
function finish($output) {
	header('Content-Type: application/json');
	echo json_encode((object) $output);
	exit;
}

# http://stackoverflow.com/a/10590242
function get_headers_from_curl_response($response) {
	$headers = array();

	$header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

	foreach (explode("\r\n", $header_text) as $i => $line) {
		if ($i === 0) {
			$headers['http_code'] = $line;
		}
		else {
			list ($key, $value) = explode(': ', $line);

			$headers[$key] = $value;
		}
	}

	return $headers;
}

function get_content_disposition_filename($response) {
	$headers = get_headers_from_curl_response($response);
	if (!array_key_exists('Content-Disposition', $headers)) {
		return null;
	}
	list ($_, $filename) = explode('"', $headers['Content-Disposition']);
	return $filename;
}

function set_error(&$output, $message) {
	$output['error'] = $message;
	error_log("(application) $message");
}
