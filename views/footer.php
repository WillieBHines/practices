 <?php if ($page != 'home') {
	 echo "
	 	</article>	
	 	</div>  
	 </main>
";
 }
 ?>

<footer class="pt-4 pb-4"><div class="container-lg container-fluid">
	<h3 class="mb-3">The World's Greatest Improv School</h3>
	<ul class="nav mb-3">
	   <?php foreach( get_nav_items() as $nav_item ){ ?>
	   <li class="nav-item"> <a class="nav-link" href="<?php echo $nav_item['href'] ?>"><?php echo $nav_item['title'] ?></a> </li>
	   <?php } ?>
	</ul>
	<div class="footer-colophon row justify-content-between mb-3">
		<div class="col-lg-7 col-sm-12">
		Send questions to Will Hines - <a href="mailto:will@wgimprovschool.com">will@wgimprovschool.com</a> | <a href="privacy.php">Privacy Policy</a>
		</div>
		<div class="col-lg-5 col-sm-12">
		Site uses: <a href="http://www.php.net/">PHP</a> / <a href="http://www.mysql.com/">MySQL</a> / <a href="http://www.getbootstrap.com/">Bootstrap</a> / <a href="http://useiconic.com/open">Open Iconic</a>
		</div>
	</div>
</div></footer>

<!-- Login Modal -->
<div class="modal fade" id="login-modal" tabindex="-1" role="dialog" aria-labelledby="Login Modal Dialog" aria-hidden="true">
	
  <div class="modal-dialog modal-dialog-centered" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		  
		<h5 class="modal-title">To Log In, We Email You A Link</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	  </div>
	  <div class="modal-body">
		<?php echo \Wbhkit\form_validation_javascript('log_in'); ?>
	
		<form id='log_in' action='index.php' method='post' novalidate>
		<?php echo \Wbhkit\hidden('ac', 'link').
		\Wbhkit\texty('email', '', 'Email', 'something@something.com', 'We will send you an email with a link to click.', 'Must be a valid email you have access to', ' required ', 'email').
		\Wbhkit\submit('Send Me An Email'); ?>
		</form>  
	</div>
	</div>
  </div>
</div>
</html>

<?php
if (TIMER) {
	echo show_hrtime();
}
?>