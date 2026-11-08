<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
render_header('Contact');
?>
<main id="main" class="page-main"><section class="page-hero"><div class="container narrow-container"><span class="eyebrow">CONTACT</span><h1>Need a hand?</h1><p>This self-hosted starter does not send contact messages. Add your preferred support mailbox or contact service before enabling a public contact form.</p><div class="coming-panel"><span aria-hidden="true">i</span><div><h2>No message is sent from this page</h2><p>We avoid presenting a form that silently discards your message. For setup help, see the project README.</p></div></div><a class="button button-outline" href="faq.php">Read the FAQ</a></div></section></main>
<?php render_footer(); ?>
