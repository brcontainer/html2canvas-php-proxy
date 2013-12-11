html2canvas-php-proxy 0.1.3
=====================

#### PHP Proxy html2canvas ####

This script allows you to use **html2canvas.js** with different servers, ports and protocols (http, https),
preventing to occur "tainted" when exporting the `<canvas>` for image.

###Problem and Solution###
When adding an image that belongs to another domain in `<canvas>` and after that try to export the canvas
for a new image, a security error occurs (actually occurs is a security lock), which can return the error:

> SecurityError: DOM Exception 18
>
> Error: An attempt was made to break through the security policy of the user agent.

### Usage ###

> Note: Requies PHP 4.3.0+

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
			<img alt="google maps static" src="http://maps.googleapis.com/maps/api/staticmap?center=40.714728,-73.998672&amp;zoom=12&amp;size=800x600&amp;maptype=roadmap&amp;sensor=false">
		</p>
		<p>
			<img alt="facebook image redirect" src="https://graph.facebook.com/1415773021975267/picture">
		</p>
	</body>
</html>
```

### Changelog ###

#### html2canvas-php-proxy 0.1.1 - 01.12.2013 ####

* Support for PHP 4.3
* Replace isset `isset($vector['var']{0})` by `isset($vector['var']) && strlen($vector['var'])>0` to prevent the error `Uninitialized string offset: 0`
* `MAX_EXEC` may not be less than 15 seconds
* Add support to bitmap files
* If the parameter "callback" has invalid characters then sets the variable `$param_callback` with `JSLOG`;
* Detects if the "host:" header was set by the client
* Remove port from `$_SERVER['HTTP_HOST']` to prevent problem in the formatting of the address
* Add function for remove old files
* Fixed "validate" callback param

#### html2canvas-php-proxy 0.1.0 - 24.11.2013 ####

* Script completely rewritten
* Added detection list "Content-length:" header
* Added alternative to callback parameter (eg. The function call is `console.log` or `alert()`, if there is no callback parameter)
* Added support for detecting max_execution_time
* Added the use of `erro_get_last()`
* Added support for "Location:" header
* Added support for detecting 304 HTTP, return an error waring (socket does not use/send Etags)
* Added `utf8_encode` to `json_encode` to prevent the error string becomes NULL
* Added an error waring, if there is no the file "Content-type:" header
* In case of HTTP 3xx response, if there is no "Location:" header, returns an error warning
* Improved response headers from proxy (`function setHeaders`)
* Improved validation http/https (`function isHttpUrl`)
* Prevent waring in `rename()` (PHP 5.2 in CGI), because the waring `return false;`
* In addition to other improvements when the script was rewritten

#### html2canvas-php-proxy 0.0.4 - 20.11.2013 ####

* Fixed tmp fileName $locationFile.$token
* Use complete URI scheme for https


### Next version ###

Details of future versions are being studied, in other words, can happen as can be forsaken ideas.
The ideas here are not ready or are not public in the main script, are only suggestions. You can offer suggestions on issues from github.

* Etag cache browser for use HTTP 304 (resources are reusable, so making unnecessary downloads)
* Cache from SOCKET, if not specified header cache in SOCKET, then uses settings by `DEFINE();`

### Others scripting language ###

You do not use PHP, but need html2canvas working with proxy, see other proxies:

* [html2canvas proxy in asp.net (csharp)](https://github.com/brcontainer/html2canvas-csharp-proxy)
* [html2canvas proxy in asp classic (vbscript)](https://github.com/brcontainer/html2canvas-asp-vbscript-proxy)
