<?php
$config = json_decode(file_get_contents('../server.json'), true);

$rcon_password = trim(file_get_contents($config['rcon_password_file_path']));
$rcon_response = 'Server is not running.';

$output = array();

$rcon_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

# First rcon for the status
$rcon_datagram = "\xff\xff\xff\xffrcon \"$rcon_password\" status"; 
if (socket_sendto($rcon_socket, $rcon_datagram, strlen($rcon_datagram), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']) === FALSE) {
	$output['error'] = 'Unfucking failed due to bad socket write on check. Contact the administrator.';
	socket_close($rcon_socket);
	finish($output);
}

# Read the status from the socket
$rcon_buffer = '';
if (socket_recv($rcon_socket, $rcon_buffer, strlen($rcon_response), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']) ===  FALSE) {
	$output['error'] = 'Unfucking failed due to bad socket read on check. Contact the administrator.';
	socket_close($rcon_socket);
	finish($output);
}

# If the server is not fucked, set an error
if ($rcon_buffer !== $rcon_response) {
	$output['error'] = "Server says: \"$rcon_buffer\"";
	socket_close($rcon_socket);
	finish($output);
}

# If it is fucked, fix it
$rcon_datagram = "\xff\xff\xff\xffrcon \"$rcon_password\" exec {$config['quake_idle_cfg']}"; 
if (socket_sendto($rcon_socket, $rcon_datagram, strlen($rcon_datagram), 0, $_SERVER['SERVER_ADDR'], $config['quake_port']) === FALSE) {
	$output['error'] = 'Unfucking failed due to bad socket write on unfuck. Contact the administrator.';
	socket_close($rcon_socket);
	finish($output);
}

socket_close($rcon_socket);
$output['success'] = "Sent unfucking command to server.";
finish($output);
function finish($output) {
	header('Content-Type: application/json');
	echo json_encode((object) $output);
	exit;
}
