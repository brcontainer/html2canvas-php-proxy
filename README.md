html2canvas-php-proxy 0.1.7
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

### Follow ###

I ask you to follow me or "star" my repository to track updates

### Usage ###

> Note: Requires PHP 4.3.0+

```html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>html2canvas php proxy</title>
		<script src="html2canvas.js"></script>
		<script>
		//<![CDATA[
		(function() {
			window.onload = function(){
				html2canvas(document.body, {
					"logging": true, //Enable log (use Web Console for get Errors and Warings)
					"proxy":"html2canvasproxy.php",
					"onrendered": function(canvas) {
						var img = new Image();
						img.onload = function() {
							img.onload = null;
							document.body.appendChild(img);
						};
						img.onerror = function() {
							img.onerror = null;
							if(window.console.log) {
								window.console.log("Not loaded image from canvas.toDataURL");
							} else {
								alert("Not loaded image from canvas.toDataURL");
							}
						};
						img.src = canvas.toDataURL("image/png");
					}
				});
			};
		})();
		//]]>
		</script>
	</head>
	<body>
		<p>
			<img alt="google maps static" src="http://maps.googleapis.com/maps/api/staticmap?center=40.714728,-73.998672&amp;zoom=12&amp;size=800x600&amp;maptype=roadmap&amp;sensor=false">
		</p>
	</body>
</html>
```

#### Using Web Cosnole ####

If you have any problems with the script recommend to analyze the log using the Web Console from your browser:
* Firefox: https://developer.mozilla.org/en-US/docs/Tools/Browser_Console
* Chrome: https://developers.google.com/chrome-developer-tools/docs/console
* InternetExplorer: http://msdn.microsoft.com/en-us/library/gg589530%28v=vs.85%29.aspx

Get NetWork results:
* Firefox: https://hacks.mozilla.org/2013/05/firefox-developer-tool-features-for-firefox-23/
* Chrome: https://developers.google.com/chrome-developer-tools/docs/network
* InternetExplorer: http://msdn.microsoft.com/en-us/library/gg130952%28v=vs.85%29.aspx

An alternative is to diagnose problems accessing the link directly:

`http://[DOMAIN]/[PATH]/html2canvasproxy.php?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

Replace `[DOMAIN]` by your domain (eg. 127.0.0.1) and replace `[PATH]` by your project folder (eg. project-1/test), something like:

`http://localhost/project-1/test/html2canvasproxy.php?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`


### Changelog ###

#### html2canvas-php-proxy 0.1.6 and 0.1.7 - 07.05.2014 ####

* Changed order of functions
* Removed `$e` variable (unused) in `json_encode`
* Fixed line `$response = 'Failed to rename the temporary file';` (`$response` is array)
* Removed incompatibility with PHP old versions (before 5.1.0) in `relative2absolute`
* Added returns "blank" in `relative2absolute` (if `scheme` invalid in `$m` parameter)
* Added detect problem in redirects (if you have multiple redirects)
* Replace `stripos` by `strpos` (php4) - version 0.1.7
* Added comparison of "socket time limit (timeout)" and "max_execution_time" (php.ini), preventing the page goes blank - version 0.1.7


#### html2canvas-php-proxy 0.1.5 - 04.05.2014 ####

* Improved "typing" for better updates/pull-request
* Converted variuos variables to (int)
* removed must-revalidate in header
* Improved forks
* Improved http response, If http_status<>200, return error in html2canvas
* Fixed bug in Facebook redirect (HTTP/1.1 302 forced.302)
* Removed unnecessary Etag-header
* Fixed undefined variables
* Fixed bug in `json_encode_string` (characters)
* Fixed "invalid escapes" in `json_encode_string`
* Improved perfomance in `json_encode_string`
* Removed `utf8_encode` (unecessary) in `json_encode_string`
* Fixed bug in `relative2absolute`
* `downloadSource` always returns array
* Replace `error_get_last` by `get_error`
* Added coments in functions


#### html2canvas-php-proxy 0.1.2 to 0.1.4 - 17.03.2014 ####

* Added support to javascript functions basead in Objects (update to 0.1.2)
* Fixed bug in 0.1.2 (update to 0.1.3)
* Added support to "relative paths" (function relative2absolute) (0.1.4)
* Added "referer header" (if exists) (0.1.4)
* Added "remove charset" for mime-types (eg. text/html; charset=ut8 => text/html) (0.1.4)
* Added prefix in files created by html2canvas-php-proxy (0.1.4)
* "remove_old_files function" removes only the files with prefix (0.1.4)


#### html2canvas-php-proxy 0.1.1 - 01.12.2013 ####

* Support for PHP 4.3
* Replace `isset($vector['var']{0})` by `isset($vector['var']) && strlen($vector['var'])>0` to prevent the error `Uninitialized string offset: 0`
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
The ideas here are not ready or are not public in the main script, are only suggestions. You can offer suggestions on [issues](https://github.com/brcontainer/html2canvas-php-proxy/issues/new).

* Etag cache browser for use HTTP 304 (resources are reusable, avoiding unnecessary downloads)
* Cache from SOCKET, if not specified header cache in SOCKET, then uses settings by `DEFINE();`

### Others scripting language ###

You do not use PHP, but need html2canvas working with proxy, see other proxies:

* [html2canvas proxy in asp.net (csharp)](https://github.com/brcontainer/html2canvas-csharp-proxy)
* [html2canvas proxy in asp classic (vbscript)](https://github.com/brcontainer/html2canvas-asp-vbscript-proxy)
