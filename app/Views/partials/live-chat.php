<?php

declare(strict_types=1);

$liveChatSite = wayfairs_public_settings();
$smartsuppKey = trim((string) ($liveChatSite['site.smartsupp_key'] ?? ''));
if ($smartsuppKey === '') {
    return;
}
?>
<!-- Smartsupp Live Chat -->
<script type="text/javascript">
var _smartsupp = _smartsupp || {};
_smartsupp.key = <?= json_encode($smartsuppKey) ?>;
window.smartsupp||(function(d) {
  var s,c,o=smartsupp=function(){ o._.push(arguments)};o._=[];
  s=d.getElementsByTagName('script')[0];c=d.createElement('script');
  c.type='text/javascript';c.charset='utf-8';c.async=true;
  c.src='https://www.smartsuppchat.com/loader.js?';s.parentNode.insertBefore(c,s);
})(document);
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank">Smartsupp</a></noscript>