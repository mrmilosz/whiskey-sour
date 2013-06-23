<?php
$pk3_download_prefix = "http://worldspawn.org/maps/downloads/";
$q3df_server_url = "http://q3df.org/serverlist#server_80";
$pk3_file_names = array_filter(array_map(function($file_path) {
	return basename($file_path);
}, glob('/home/q3ds/.q3a/defrag/*.pk3')), function($file_name) {
	return !preg_match('/^z+-/', $file_name);
});
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Whiskey Sour</title>
		<meta charset="utf-8" />
		<style type="text/css">
html,
body {
	padding: 0;
	margin: 0;
}

html {
	background-color: rgba(255, 255, 255, 1);
	background-image: url('/static/whiskey_sour.jpg');
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
	var performancePollPeriod = 5000;
	var validPropertyNames = ['pcpu', 'pmem', 'etime', 'comm'];

	function pollPerformance() {
		var psFormatString = Array.prototype.map.call(document.querySelectorAll('.info .performance .field'), function(spanElement) {
			return validPropertyNames.filter(function(propertyName) {
				return spanElement.classList.contains(propertyName);
			})[0];
		}).join(',');

		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/script/performance.php?o=' + psFormatString, true);
		xhr.onreadystatechange = function() {
			if (this.readyState === 4) {
				if (this.status === 200) {
					var properties = JSON.parse(this.responseText),
						serverIsOnline = false;

					Array.prototype.forEach.call(document.querySelectorAll('.info .performance .field'), function(spanElement) {
						spanElement.textContent = '';
					});

					for (propertyName in properties) {
						var propertyValue = properties[propertyName];
						document.querySelector('.info .performance .field.' + propertyName).textContent = propertyValue;
						serverIsOnline = true;
					}

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
		<h1><a href="<?php echo $q3df_server_url; ?>">Whiskey <span class="green">Sour</span> <span class="red">|</span> Mixed</a></h1>
		<h2 class="tech"><a href="defrag://<?php echo $_SERVER['SERVER_ADDR']; ?>:27960"><?php echo implode('<span class="light">.</span>', explode('.', $_SERVER['SERVER_ADDR'])); ?><span class="light">:</span>27960</a></h2>
		<div class="info">
			<div class="status section">
				<span class="down entry">server is down</span>
				<span class="up entry tech"><a href="defrag://<?php echo $_SERVER['SERVER_NAME']; ?>">/connect <?php echo $_SERVER['SERVER_NAME']; ?></a></span>
			</div>
			<div class="performance section">
				<span class="entry"><span class="comm field"></span></span>
				<span class="entry">cpu: <span class="pcpu field"></span>%</span>
				<span class="entry">mem: <span class="pmem field"></span>%</span>
				<span class="entry">uptime: <span class="etime field"></span></span>
			</div>
		</div>
		<div class="pk3">
<?php foreach ($pk3_file_names as $pk3_file_name): ?>
<?php $angle = rand() % 10 - 5; ?>
			<a href="<?php echo "$pk3_download_prefix$pk3_file_name"; ?>" style="transform: rotate(<?php echo $angle; ?>deg); -webkit-transform: rotate(<?php echo $angle; ?>deg);"><?php echo $pk3_file_name; ?></a>
<?php endforeach; ?>
		</div>
	</body>
</html>
