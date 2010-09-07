<html>
<head>
	<title>AJAX with jQuery Example</title>
	<script type="text/javascript" src="/public/js/jquery.js"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			timestamp = 0;
			updateMsg();
			hideLoading();
			$("form#chatform").submit(function(){
				showLoading();								
				$.post("/chat/backend",{
							message: $("#content").val(),
							name: $("#name").val(),
							action: "postmsg",
							time: timestamp
						}, function(xml) {
					addMessages(xml);
					$("#content").val("");
					hideLoading();
					$("#content").focus();
				});		
				return false;
			});
		});
		function rmContent(){
			
		}
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
			if($("status",xml).text() == "2") return;
			timestamp = $("time",xml).text();
			$("message",xml).each(function(id) {
				message = $("message",xml).get(id);
				$("#messagewindow").prepend("<b>"+$("author",message).text()+
											"</b>: "+$("text",message).text()+
											"<br />");
			});

			
		}
		function updateMsg() {
			$.post("/chat/backend",{ time: timestamp }, function(xml) {
				$("#loading").remove();				
				addMessages(xml);
			});
			setTimeout('updateMsg()', 4000);
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
	<img src="/public/images/blueloading.gif" alt="Loading data, please wait...">  
	</div><br />
	
	<input type="submit" value="ok" /><br />
	</form>
	</div>
</body>
</html>