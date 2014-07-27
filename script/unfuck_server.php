<?php
$config = json_decode(file_get_contents('../server.json'), true);

$rcon_password = trim(file_get_contents($config['rcon_password_file_path']));
$rcon_response = 'Server is not running.';

$output = array();

# Check if the server is fucked
$rcon_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$rcon_datagram = "\xff\xff\xff\xffrcon \"$rcon_password\" status"; 
socket_sendto($rcon_socket, $rcon_datagram, strlen($rcon_datagram), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']);

# If it is not fucked, set an error
if (socket_read($rcon_socket, strlen($rcon_response)) !== $rcon_response) {
	$output['error'] = 'Server does not appear fucked though…';
	socket_close($rcon_socket);
	finish($output);
}

# If it is fucked, fix it
$rcon_datagram = "\xff\xff\xff\xffrcon \"$rcon_password\" exec cfgs/idle.cfg"; 
socket_sendto($rcon_socket, $rcon_datagram, strlen($rcon_datagram), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']);
socket_close($rcon_socket);

$output['success'] = "Sent unfucking command to server.";
finish($output);
function finish($output) {
	header('Content-Type: application/json');
	echo json_encode((object) $output);
	exit;
}
