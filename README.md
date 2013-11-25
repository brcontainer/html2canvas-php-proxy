html2canvas-php-proxy 0.1.0
=====================

#### PHP Proxy html2canvas ( php 5.0+ ) ####

This script allows you to use **html2canvas.js** with different servers, ports and protocols (http, https),
preventing to occur "tainted" when exporting the `<canvas>` for image.

###Problem and Solution###
When adding an image that belongs to another domain in `<canvas>` and after that try to export the canvas
for a new image, a security error occurs (actually occurs is a security lock), which can return the error:

> SecurityError: DOM Exception 18
>
> Error: An attempt was made to break through the security policy of the user agent.

### Usage ###

```html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>html2canvas php proxy</title>
		<script src="html2canvas.js"></script>
		<script>
		window.onload = function(){
		  html2canvas( [ document.body ], {
				"proxy":"html2canvasproxy.php",
				"onrendered": function(canvas) {
					var uridata = canvas.toDataURL("image/png");
					window.open(uridata);
				}
			});
		};
		</script>
	</head>
	<body>
		<p>
			<img alt="google maps static" src="http://maps.googleapis.com/maps/api/staticmap?center=40.714728,-73.998672&zoom=12&size=400x400&maptype=roadmap&sensor=false">
		</p>
	</body>
</html>
```

### Changelog ###

#### html2canvas-php-proxy 0.1.0 24.11.2013 ####

* Script completely rewritten
* Added detection list "Content-length:"
* Added alternative to callback parameter
* Added support for detecting max_execution_time
* Added support for "Location:"
* Added detect whether there was "Location:" if the response type 3xx chance the header does not exist, returns error
* Improved response headers from proxy
* Improved validation http/https
* In addition to other improvements when the script was rewritten

#### html2canvas-php-proxy 0.0.4 20.11.2013 ####

* Fixed tmp fileName $locationFile.$token
* Use complete URI scheme for https
