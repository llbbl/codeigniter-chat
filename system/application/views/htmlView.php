<html>
<head>
	<title>CodeIgniter Shoutbox</title>
	<script type="text/javascript" src="/js/jquery-1.4.2.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			
			loadMsg();			
			hideLoading();
						
			$("form#chatform").submit(function(){
											
				$.post("/chat/update",{
							message: $("#content").val(),
							name: $("#name").val(),
							action: "postmsg"
						}, function() {
					
					$("#messagewindow").prepend("<b>"+$("#name").val()+"</b>: "+$("#content").val()+"<br />");
					
					$("#content").val("");					
					$("#content").focus();
				});		
				return false;
			});
			
			
		});

		function showLoading(){
			$("#contentLoading").show();
			$("#txt").hide();
			$("#author").hide();
		}
		function hideLoading(){
			$("#contentLoading").hide();
			$("#txt").show();
			$("#author").show();
		}
		
		function addMessages(xml) {
			
			$(xml).find('message').each(function() {
				
				author = $(this).find("author").text();
				msg = $(this).find("text").text();
				
				$("#messagewindow").append("<b>"+author+"</b>: "+msg+"<br />");
			});
			
		}
		
		function loadMsg() {
			$.get("/chat/backend", function(xml) {
				$("#loading").remove();				
				addMessages(xml);
			});
			
			//setTimeout('loadMsg()', 4000);
		}
	</script>
	<style type="text/css">
		#messagewindow {
			height: 250px;
			border: 1px solid;
			padding: 5px;
			overflow: auto;
		}
		#wrapper {
			margin: auto;
			width: 438px;
		}
	</style>
</head>
<body>
	<div id="wrapper">
	<p id="messagewindow"><span id="loading">Loading...</span></p>
	<form id="chatform">
	<div id="author">
	Name: <input type="text" id="name" />
	</div><br />

	<div id="txt">
	Message: <input type="text" name="content" id="content" value="" />
	</div>
	
	<div id="contentLoading" class="contentLoading">  
	<img src="/images/blueloading.gif" alt="Loading data, please wait...">  
	</div><br />
	
	<input type="submit" value="ok" /><br />
	</form>
	</div>
</body>
</html>