<?php
$config = json_decode(file_get_contents('server.json'), true);
$db_path = 'records.db';

# Establish a connection to the pk3 name database
$database = new SQLite3($db_path);
$statement = $database->prepare('SELECT name FROM pack ORDER BY name');
$result = $statement->execute();
$pk3_file_names = array();
while ($row = $result->fetchArray()) {
	$pk3_file_names []= "${row['name']}.pk3";
}
$result->finalize();
$statement->close();
?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo $config['title']; ?></title>
		<meta charset="utf-8" />
		<style type="text/css">
html,
body {
	padding: 0;
	margin: 0;
}

html {
	background-color: rgba(255, 255, 255, 1);
	background-image: url('<?php echo $config['background_image_url']; ?>');
	background-position: top center;
	background-repeat: no-repeat;
	font-family: "Arial",sans-serif;
	color: rgba(0, 0, 0, 1);
	font-size: 14px;
}

body {
	padding-top: 105px;
}

h1 {
	text-align: center;
	text-shadow: 1px 1px 0px rgba(255, 255, 255, 1);
	margin: 0;
	font-weight: bold;
	font-size: 400%;
}

h2 {
	text-align: center;
	margin: 0;
	font-weight: normal;
	font-size: 200%;
}

a {
	color: inherit;
	text-decoration: inherit;
}

.tech {
	font-family: "Lucida Sans Console",monospace;
}

.green {
	color: rgba(0, 127, 0, 1);
}

.red {
	color: rgba(255, 0, 0, 1);
}

.light {
	color: rgba(0, 0, 0, 0.5);
}

.info {
	text-align: center;
	font-size: 0;
	margin-bottom: 30px;
}

.info .section {
	margin: 10px auto 0;
}

.info .performance.section {
	margin-top: 30px;
}

.info .section .entry {
	font-size: 14px;
	margin: 0 0.5em;
}

.info .status.section .entry.up,
.info.online .status.section .entry.down,
.info .performance.section {
	display: none;
}

.info.online .status.section .entry.up,
.info .status.section .entry.down {
	display: inline;
}
.info.online .performance.section {
	display: block;
}

.info .status.section .entry.up {
	color: rgba(0, 127, 0, 1);
}

.info .status.section .entry.down {
	color: rgba(127, 0, 0, 1);
}

.info .performance.section .entry {
	color: rgba(0, 0, 0, 0.75);
}

.add-pk3 {
	position: relative;
	text-align: center;
	margin-bottom: 3em;
}

.add-pk3 > * {
	vertical-align: middle;
}

.add-pk3 input[type="text"] {
	border-width: 1px;
	border-style: solid;
	border-color: #dddddd;
	border-radius: 3px;
	padding: 2px 2em 2px 2px;
	box-shadow: 0 0 10px #eeeeee;
}

.add-pk3 input[type="text"]:focus {
	outline: none;
}

.add-pk3 .response,
.add-pk3 .status {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
}
.add-pk3 .success.response {
	color: rgba(0, 127, 0, 1);
}
.add-pk3 .error.response {
	color: rgba(127, 0, 0, 1);
}
.add-pk3 .status {
	color: rgba(0, 0, 0, 0.75);
}

.pk3 {
	text-align: center;
	font-size: 0;
	width: 50%;
	margin: 15px auto 0;
}

.pk3 a {
	color: rgba(0, 0, 0, 0.5);
	display: inline-block;
	font-size: 14px;
	margin: 0 0.5em;
}
		</style>
		<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	var performancePollPeriod = 30000;
	var validPropertyNames = ['pcpu', 'pmem', 'etime', 'time', 'comm'];
	var previousProperties = {
		time: null,
		etime: null
	};

	// Adds etime and time to the properties if pcpu was requested
	function adjustOutgoingPropertyNames(outgoingPropertyNames) {
		outgoingPropertyNames = outgoingPropertyNames.slice(0);
		if (outgoingPropertyNames.indexOf('pcpu') > -1) {
			if (outgoingPropertyNames.indexOf('etime') < 0) {
				outgoingPropertyNames.push('etime');
			}
			if (outgoingPropertyNames.indexOf('time') < 0) {
				outgoingPropertyNames.push('time');
			}
			if (previousProperties.time !== null && previousProperties.etime !== null) {
				outgoingPropertyNames.splice(outgoingPropertyNames.indexOf('pcpu'), 1);
			}
		}
		return outgoingPropertyNames;
	}

	// Uses historical data on etime and time to compute a more up-to-date pcpu
	function adjustIncomingProperties(outgoingPropertyNames, incomingProperties) {
		var adjustedIncomingProperties = {};
		outgoingPropertyNames.forEach(function(propertyName) {
			if (propertyName === 'pcpu') {
				if (previousProperties.time !== null && previousProperties.etime !== null) {
					adjustedIncomingProperties.pcpu = (100 *
						(parsePsTime(incomingProperties.time) - parsePsTime(previousProperties.time)) /
						(parsePsTime(incomingProperties.etime) - parsePsTime(previousProperties.etime))
					).toFixed(1);
				}
				else {
					adjustedIncomingProperties.pcpu = incomingProperties.pcpu;
				}
				previousProperties.time = incomingProperties.time;
				previousProperties.etime = incomingProperties.etime;
			}
			else {
				adjustedIncomingProperties[propertyName] = incomingProperties[propertyName];
			}
		});
		return adjustedIncomingProperties;
	}

	// Parses [[DD-]hh:]mm:ss or [DD-]HH:MM:SS (unix ps time formats) into seconds
	function parsePsTime(psTime) {
		var days = 0,
			hours = 0,
			minutes = 0,
			seconds = 0;

		var parts = psTime.split('-');

		if (parts.length === 2) {
			days = parseInt(parts[0]);
		}

		parts = parts[parts.length - 1].split(':');

		if (parts.length >= 2) {
			seconds = parseInt(parts[parts.length - 1]);
			minutes = parseInt(parts[parts.length - 2]);
		}

		if (parts.length >= 3) {
			hours = parseInt(parts[parts.length - 3]);
		}

		return days * 86400 + hours * 3600 + minutes * 60 + seconds;
	}

	document.querySelector('.add-pk3').addEventListener('submit', function(event) {
		var self = this;

		Array.prototype.forEach.call(self.querySelectorAll('.response'), function(responseElement) {
			responseElement.textContent = '';
		});
		self.querySelector('.status').textContent = 'Reticulating splines…';

		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/script/map_download.php?' + (['name', 'ext']).map(function(key) {
			return key + '=' + encodeURIComponent(self.querySelector('.field[name="' + key + '"]').value)
		}).join('&'), true);
		xhr.onreadystatechange = function() {
			if (this.readyState === 4) {
				if (this.status === 200) {
					var response = JSON.parse(this.responseText);
					if (response.error) {
						self.querySelector('.error.response').textContent = response.error;
					}
					else if (response.success) {
						self.querySelector('.success.response').textContent = response.success;
					}
					
					self.querySelector('.status').textContent = '';
				}
			}
		};
		xhr.send();

		event.preventDefault && event.preventDefault();
		event.cancelBubbling && event.cancelBubbling();	
		return false;
	});

	function pollPerformance() {
		var requestedPropertyNames = Array.prototype.map.call(document.querySelectorAll('.info .performance .field'), function(spanElement) {
			return validPropertyNames.filter(function(propertyName) {
				return spanElement.classList.contains(propertyName);
			})[0];
		});

		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/script/performance.php?o=' + encodeURIComponent(adjustOutgoingPropertyNames(requestedPropertyNames).join(',')), true);
		xhr.onreadystatechange = function() {
			if (this.readyState === 4) {
				if (this.status === 200) {
					var returnedProperties = adjustIncomingProperties(requestedPropertyNames, JSON.parse(this.responseText)),
						serverIsOnline = false;

					Array.prototype.forEach.call(document.querySelectorAll('.info .performance .field'), function(spanElement) {
						spanElement.textContent = '';
					});

					requestedPropertyNames.forEach(function(propertyName) {
						var propertyValue = returnedProperties[propertyName];
						if (propertyValue !== undefined) {
							document.querySelector('.info .performance .field.' + propertyName).textContent = propertyValue;
							serverIsOnline = true;
						}
					});

					if (serverIsOnline) {
						document.querySelector('.info').classList.add('online');
					}
					else {
						document.querySelector('.info').classList.remove('online');
					}
				}
				setTimeout(pollPerformance, performancePollPeriod);
			}
		};
		xhr.send();
	}

	pollPerformance();
});
		</script>
	</head>
	<body>
		<h1><a href="<?php echo $config['q3df_server_url']; ?>"><?php echo $config['heading']; ?></a></h1>
		<h2 class="tech"><a href="defrag://<?php echo $_SERVER['SERVER_ADDR']; ?>:<?php echo $config['quake_port']; ?>"><?php echo implode('<span class="light">.</span>', explode('.', $_SERVER['SERVER_ADDR'])); ?><span class="light">:</span>27960</a></h2>
		<div class="info">
			<div class="status section">
				<span class="down entry">server status unknown</span>
				<span class="up entry tech"><a href="defrag://<?php echo $_SERVER['SERVER_NAME']; ?>">/connect <?php echo $_SERVER['SERVER_NAME']; ?></a></span>
			</div>
			<div class="performance section">
				<span class="entry"><span class="comm field"></span></span>
				<span class="entry">cpu: <span class="pcpu field"></span>%</span>
				<span class="entry">mem: <span class="pmem field"></span>%</span>
				<span class="entry">uptime: <span class="etime field"></span></span>
			</div>
		</div>
		<form class="add-pk3">
			<span>Add maps from <a href="<?php echo $config['pk3_download_source_homepage_url']; ?>"><?php echo $config['pk3_download_source_name']; ?></a>:</span>
			<input class="field" type="text" name="name" />
			<select class="field" name="ext">
				<option value="pk3" selected>.pk3</option>
				<option value="bsp">.bsp</option>
			</select>
			<input type="submit" value="Download" />
			<div class="success response"></div>
			<div class="error response"></div>
			<div class="status"></div>
		</form>
		<div class="pk3">
<?php foreach ($pk3_file_names as $pk3_file_name): ?>
<?php $angle = rand() % 10 - 5; ?>
			<a href="<?php echo "${config['pk3_download_url_prefix']}$pk3_file_name"; ?>" style="transform: rotate(<?php echo $angle; ?>deg); -webkit-transform: rotate(<?php echo $angle; ?>deg);"><?php echo $pk3_file_name; ?></a>
<?php endforeach; ?>
		</div>
	</body>
</html>
