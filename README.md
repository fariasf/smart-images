# Smart Images

Plugin to smart-load WordPress images (both featured image and in-content images) as the user scrolls.

On initial page load, images will be replaced with a blurred version (like Medium does), with a solid color (like Google Image Search does) or with a transparent 1x1 GIF or PNG, either using external requests or embedding the image source encoded in base64. It uses https://github.com/verlok/lazyload to detect the users' scroll and replace the image sources.

This technique will reduce the initial page load time, and the impact will be bigger the more and bigger images the page has. In our test pages, improved PLT by a few seconds (in cases of content with few images) to as much as 30 seconds, in big pieces of content with lot of images.

This plugin was initially developed for RD.com. Many thanks to TMBI for sponsoring the development of the plugin, specially to Mikel King and Nick Contardo for making it possible to publish it.
