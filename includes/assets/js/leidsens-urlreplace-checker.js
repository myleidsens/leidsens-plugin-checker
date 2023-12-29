jQuery(document).ready(function() {
	jQuery('.leidsens_pages').select2();
    jQuery('.leidsens_posts').select2();

    leidsens_posts_div = document.getElementById("leidsens_posts_div");
    leidsens_pages_div = document.getElementById("leidsens_pages_div");
    leidsens_posts_div.style.display = "none";
    leidsens_pages_div.style.display = "none";

    jQuery( "input[type=radio]" ).on( "click", function() {
    	leidsens_posts_div.style.display = "none";
    	leidsens_pages_div.style.display = "none";
		var div = jQuery( this ).val();
		var leidsens_div = document.getElementById(div+"_div");
		alert(jQuery( this ).val());
		leidsens_div.style.display = "block";

	});
});