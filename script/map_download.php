<?php
$map_source_url_format = 'http://worldspawn.org/maps/downloads/%s.pk3';
$map_destination_path_format = '/home/q3ds/.q3a/defrag/%s.pk3';
$curl_user_agent_string = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';
$rcon_password = trim(file_get_contents('/home/q3ds/.rcon_password'));
$rcon_command = "\xff\xff\xff\xffrcon \"$rcon_password\" reload_fs";
$rcon_ip = '199.195.252.136';
$rcon_port = 27960;

$output = array();

# Get the name of the pk3 file to be downloaded
$client_pk3_filename = isset($_GET['pk3']) ? $_GET['pk3'] : '';

# Sanitize the pk3 name and check if it already exists on the system
if (strpos($client_pk3_filename, '/') > -1) {
	$output['error'] = "Invalid character `/' in filename.";
	finish($output);
}
$map_destination_path = sprintf($map_destination_path_format, $client_pk3_filename);
if (file_exists($map_destination_path)) {
	$output['error'] = "File already exists: `$client_pk3_filename.pk3'.";
	finish($output);
}

# Sanitize the pk3 name and create the URL
$map_source_url = sprintf($map_source_url_format, urlencode($client_pk3_filename));

# Check if the pk3 exists at the source URL
$map_source_head_curl = curl_init($map_source_url);
curl_setopt($map_source_head_curl, CURLOPT_USERAGENT, $curl_user_agent_string);
curl_setopt($map_source_head_curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($map_source_head_curl, CURLOPT_NOBODY, true);
curl_exec($map_source_head_curl);
$map_source_head_curl_http_code = curl_getinfo($map_source_head_curl, CURLINFO_HTTP_CODE);
curl_close($map_source_head_curl);
if ($map_source_head_curl_http_code !== 200) {
	$output['error'] = "Server responded with code $map_source_head_curl_http_code for URL `$map_source_url'.";
	finish($output);
}

# Download the pk3
$map_destination_file = fopen($map_destination_path, 'wb');
$map_source_curl = curl_init($map_source_url);
curl_setopt($map_source_curl, CURLOPT_USERAGENT, $curl_user_agent_string);
curl_setopt($map_source_curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($map_source_curl, CURLOPT_HEADER, false);
curl_setopt($map_source_curl, CURLOPT_FILE, $map_destination_file);
curl_exec($map_source_curl);
curl_close($map_source_curl);
fclose($map_destination_file);

# Reload the Q3 filesystem
$rcon_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_sendto($rcon_socket, $rcon_command, strlen($rcon_command), 0, $rcon_ip, $rcon_port);
socket_close($rcon_socket);

$output['success'] = "Successfully downloaded `$map_source_url'! Reloading filesystem.";
$output['filename'] = "$client_pk3_filename.pk3";
finish($output);
function finish($output) {
	header('Content-Type: application/json');
	echo json_encode((object) $output);
	exit;
}
