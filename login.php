<?php
/*\
|*|  ----------------------------
|*|  --- [  Oli login page  ] ---
|*|  ----------------------------
|*|  
|*|  The official Oli login page
|*|  
|*|  Edited by Mathieu Guérin (aka "Matiboux"), for Oli
|*|  Original template by Andy Tran (http://codepen.io/andytran/pen/PwoQgO)
|*|  
|*|  Oli website: https://oliframework.github.io/Oli/
|*|  Oli Github repository: https://github.com/OliFramework/Oli/
|*|  
|*|  Once the framework is correctly configured,
|*|  Just place this file in content/theme/ directory
|*|  You'll be able to open it at yourwebsite.com/login/
|*|  
|*|  --- --- ---
|*|  
|*|  MIT License
|*|  
|*|  Copyright (c) 2015 Mathieu Guérin (aka "Matiboux")
|*|  
|*|  Permission is hereby granted, free of charge, to any person obtaining a copy
|*|  of this software and associated documentation files (the "Software"), to deal
|*|  in the Software without restriction, including without limitation the rights
|*|  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
|*|  copies of the Software, and to permit persons to whom the Software is
|*|  furnished to do so, subject to the following conditions:
|*|  
|*|  The above copyright notice and this permission notice shall be included in all
|*|  copies or substantial portions of the Software.
|*|  
|*|  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
|*|  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
|*|  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
|*|  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
|*|  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
|*|  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
|*|  SOFTWARE.
\*/

if(!$_Oli->getAccountsManagementStatus()) header('Location: ' . $_Oli->getUrlParam(0));

if($_Oli->getUrlParam(2) == 'logout') {
	if(!$_Oli->isExistAuthKey()) $resultCode = 'S:You tried to logout but you\'re not connected';
	else if($_Oli->logoutAccount()) $resultCode = 'S:You have been disconnected';
	else $resultCode = 'E:An error occurred while disconnecting you';
}
else if($_Oli->getUserRightLevel() >= $_Oli->translateUserRight('USER')) header('Location: ' . $_Oli->getUrlParam(0));
else if($_Oli->getUrlParam(2) == 'activate' AND !empty($_Oli->getUrlParam(3))) {
	if(!$requestsInfos = $_Oli->getAccountLines('REQUESTS', array('activate_key' => $_Oli->getUrlParam(3)))) $resultCode = 'E:The request you called does not exist';
	else if($requestsInfos['action'] != 'activate') $resultCode = 'E:The request you called does not allow you to activate your account';
	else {
		if(strtotime($requestsInfos['expire_date']) < time()) $resultCode = 'E:The request has expired, so we could not activate your account';
		else {
			$queryResult = $_Oli->deleteAccountLines('REQUESTS', array('activate_key' => $_Oli->getUrlParam(3)));
			if($queryResult) $queryResult = $_Oli->updateUserRight('USER', $requestsInfos['username']);
				
			if($queryResult) $resultCode = 'S:Your account has been successfully activated';
			else $resultCode = 'E:An error occurred while activating your account';
		}
	}
}
else if($_Oli->issetPostVars()) {
	if($_Oli->getUrlParam(2) == 'change-password' AND !empty($_Oli->getUrlParam(3))) {
		if($_Oli->isEmptyPostVars('newPassword')) $resultCode = 'E:You did not enter your new password';
		else if(!$requestsInfos = $_Oli->getAccountLines('REQUESTS', array('activate_key' => $_Oli->getUrlParam(3)))) $resultCode = 'E:The request you called does not exist';
		else if($requestsInfos['action'] != 'change-password') $resultCode = 'E:The request you called does not allow you to change your password';
		else {
			if(strtotime($requestsInfos['expire_date']) < time()) $resultCode = 'E:The request has expired, so we could not change your password';
			else {
				$queryResult = $_Oli->deleteAccountLines('SESSIONS', $requestsInfos['username']);
				if($queryResult) $queryResult = $_Oli->deleteAccountLines('REQUESTS', array('activate_key' => $_Oli->getUrlParam(3)));
				if($queryResult) $queryResult = $_Oli->updateAccountInfos('ACCOUNTS', array('password' => $_Oli->hashPassword($_Oli->getPostVars('newPassword'))), $requestsInfos['username']);
					
				if($queryResult) {
					$hideChangePasswordUI = true;
					$resultCode = 'S:Your password has been successfully changed';
				}
				else $resultCode = 'S:An error occurred while activating your account';
			}
		}
	}
	else if($_Oli->getUrlParam(2) == 'recover') {
		if($_Oli->isEmptyPostVars('email')) $resultCode = 'E:You did not enter your email';
		else if(!$username = $_Oli->getAccountInfos('ACCOUNTS', 'username', array('email' => $_Oli->getPostVars('email')), false)) $resultCode = 'E:This account does not exists';
		else {
			if($requestInfos = $_Oli->getAccountLines('REQUESTS', array('username' => $username, 'action' => 'change-password')) AND strtotime($requestInfos['expire_date']) >= time()) $resultCode = 'E:A request to change your password already exists, so we could not create another one';
			else if(!$activateKey = $_Oli->createRequest($username, 'change-password')) $resultCode = 'E:An error occurred while creating the request';
			else {
				$message = '<b>Hey ' . $username . '</b>, <br /> <br />';
				$message .= '<b>One more step!</b> <br />';
				$message .= 'You still want to change your password? Just <a href="' . $_Oli->getShortcutLink('login') . '/change-password/' . $activateKey . '">go there to change it</a> (' . $_Oli->getShortcutLink('login') . '/change-password/' . $activateKey . ') <br />';
				$message .= 'You have ' . ($expireDelay = $_Oli->getRequestsExpireDelay() /3600 /24) . ' ' . ($expireDelay > 1 ? 'days' : 'day') . ' to confirm the request <br /> <br />';
				$message .= 'If you don\'t want to change it, please cancel the request in your account settings (or just ignore this mail) <br /> <br />';
				$message .= 'You got this mail from <a href="' . $_Oli->getUrlParam(0) . '">' . $_Oli->getSetting('name') . '</a> <br />';
				$message .= '<a href="' . $_Oli::OLI_URL . '">Powered by Oli</a>';
				
				$headers = 'From: noreply@' . $this->getUrlParam('domain') . "\r\n";
				$headers .= 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1';
				
				if($mailStatus = mail($_Oli->getPostVars('email'), 'Change your password', utf8_decode($message), $headers)) {
					$hideRecoverUI = true;
					$resultCode = 'S:The request has been successfully created and a mail has been sent to you';
				}
				else {
					$_Oli->deleteAccountLines('REQUESTS', array('activate_key' => $activateKey));
					$resultCode = 'E:An error occurred while sending the confirmation mail';
				}
			}
		}
	}
	else {
		if($_Oli->isEmptyPostVars('username')) $resultCode = 'E:You did not enter your username';
		else if($_Oli->isEmptyPostVars('password')) $resultCode = 'E:You did not enter your password';
		else {
			$username = trim($_Oli->getPostVars('username'));
			if($_Oli->issetPostVars('email')) {
				if($_Oli->isEmptyPostVars('email')) $resultCode = 'E:You did not enter your email';
				else if($_Oli->isProhibitedUsername($username)) $resultCode = 'E:You can\'t take this username';
				else if($_Oli->isExistAccountInfos('ACCOUNTS', $_Oli->getPostVars('username'), false)) $resultCode = 'E:This account already exists!';
				else {
					if($_Oli->getRegisterVerificationStatus()) {
						$subject = 'Your account have been created';
						$message = '<b>Hey ' . $username . '</b>, <br /> <br />';
						$message .= '<b>One more step!</b> <br />';
						$message .= 'You just need to activate your account! Visit <a href="' . $_Oli->getShortcutLink('login') . 'login/activate/' . $activateKey . '">this page to activate it</a> (' . $_Oli->getShortcutLink('login') . 'login/activate/' . $activateKey . ') <br />';
						$message .= 'You have ' . ($_Oli->getRequestsExpireDelay() /3600 /24) . ' ' . ($expireDelay > 1 ? 'days' : 'day') . ' to confirm the request <br /> <br />';
						$message .= 'If you don\'t activate your account, it will be suspended after this delay (then deleted if someone register with the same username) <br /> <br />';
						$message .= 'You got this mail from <a href="' . $_Oli->getUrlParam(0) . '">' . $_Oli->getSetting('name') . '</a> <br />';
						$message .= '<a href="' . $_Oli->getOliInfos('website_url') . '">Powered by Oli</a>';
					}
					else {
						$subject = 'Your account have been created';
						$message = '<b>Hey ' . $username . '</b>, <br /> <br />';
						$message .= '<b>Yay! Your account have been successfully created</b> <br />';
						$message .= 'You can <a href="' . $_Oli->getShortcutLink('login') . 'login/' . $activateKey . '">connect to it on this page</a> (' . $_Oli->getShortcutLink('login') . 'login/' . $activateKey . ') <br />';
						$message .= 'You have ' . ($expireDelay = $_Oli->getRequestsExpireDelay() /3600 /24) . ' ' . ($expireDelay > 1 ? 'days' : 'day') . ' to confirm the request <br /> <br />';
						$message .= 'If you don\'t activate your account, it will be suspended after this delay (then deleted if someone register with the same username) <br /> <br />';
						$message .= 'You got this mail from <a href="' . $_Oli->getUrlParam(0) . '">' . $_Oli->getSetting('name') . '</a> <br />';
						$message .= '<a href="' . $_Oli->getOliInfos('website_url') . '">Powered by Oli</a>';
					}
					
					$headers = 'From: noreply@' . $this->getUrlParam('domain') . "\r\n";
					$headers .= 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1';
					
					if($_Oli->registerAccount($username, $_Oli->getPostVars('password'), strtolower(trim($_Oli->getPostVars('email'))), $subject, $message, $headers)) {
						if($_Oli->getRegisterVerificationStatus()) $resultCode = 'S:One more thing! A mail has been sent to you to activate your account';
						else $resultCode = 'S:Your account has been successfully created';
					}
					else $resultCode = 'E:An error occurred while creating your account';
				}
			}
			else {
				if(!$_Oli->isExistAccountInfos('ACCOUNTS', $username, false)) $resultCode = 'E:This account does not exists';
				else if($_Oli->getUserRightLevel($username) == $_Oli->translateUserRight('NEW-USER')) $resultCode = 'E:Your account is not activated';
				else if($_Oli->getUserRightLevel($username) == $_Oli->translateUserRight('BANNED')) $resultCode = 'E:Your account has been banned';
				else if($_Oli->getUserRightLevel($username) < $_Oli->translateUserRight('USER')) $resultCode = 'E:Your account is not allowed to connect';
				else if(!$_Oli->verifyLogin($username, $_Oli->getPostVars('password'))) $resultCode = 'E:You used a wrong password';
				else {
					$loginDuration = $_Oli->getPostVars('rememberMe') ? 15*24*3600 : 24*3600; // 15 days : 1 day
					if($_Oli->loginAccount($username, $_Oli->getPostVars('password'), $loginDuration)) {
						if(!empty($_Oli->getPostVars('referer'))) header('Location: ' . $_Oli->getPostVars('referer'));
						else header('Location: ' . $_Oli->getUrlParam(0));
					}
					else $resultCode = 'E:An error occurred while logging you in';
				}
			}
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Login Page for Oli (<?php echo $_Oli->getOliInfos('website_url'); ?>)" />
<meta name="keywords" content="Login,page,Oli,PHP,Framework,Mathieu,Guérin,Mati,Matiboux" />
<meta name="author" content="Mathieu Guérin, matiboux@gmail.com" />

<style>html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,b,u,i,center,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td,article,aside,canvas,details,embed,figure,figcaption,footer,header,hgroup,menu,nav,output,ruby,section,summary,time,mark,audio,video{margin:0;padding:0;border:0;font-size:100%;font:inherit;vertical-align:baseline}article,aside,details,figcaption,figure,footer,header,hgroup,menu,nav,section{display:block}body{line-height:1}ol,ul{list-style:none}blockquote,q{quotes:none}blockquote:before,blockquote:after,q:before,q:after{content:'';content:none}table{border-collapse:collapse;border-spacing:0}</style>
<link rel='stylesheet prefetch' href='http://fonts.googleapis.com/css?family=Roboto:400,100,300,500,700,900|RobotoDraft:400,100,300,500,700,900'>
<link rel='stylesheet prefetch' href='http://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css'>
<style>body{background:#e9e9e9;color:#666;font-family:'RobotoDraft', 'Roboto', sans-serif;font-size:14px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.header{padding:50px 0;text-align:center;letter-spacing:2px}.header h1{margin:0 0 20px;font-size:48px;font-weight:400}@media(max-width: 420px){.header h1{font-size:36px;}}.header h1 a{color:#0084b4;font-weight:400;text-decoration:none}.header span{font-size:14px}@media(max-width: 420px){.header span{font-size:12px;}}.header span .fa{color:#0084b4}.header span a{color:#0084b4;font-weight:600;text-decoration:none}.message,.form-module{position:relative;background:#fff;max-width:320px;width:100%;margin:0 auto 30px;border-top:5px solid #0084b4;box-shadow:0 0 3px rgba(0, 0, 0, 0.1)}.message.message-error{border-top:5px solid #d9534f}.message .content{padding:20px 40px}.message h2{color:#555;font-size:16px;font-weight:400;line-height:1}.form-module .toggle{cursor:pointer;position:absolute;top:-0;right:-0;background:#0084b4;width:30px;height:30px;margin:-5px 0 0;color:#fff;font-size:12px;line-height:30px;text-align:center}.form-module .toggle .tooltip{position:absolute;top:8px;right:40px;display:block;background:rgba(0, 0, 0, 0.6);width:auto;padding:5px;font-size:10px;line-height:1;text-transform:uppercase}.form-module .toggle .tooltip:before{content:'';position:absolute;top:5px;right:-5px;display:block;border-top:5px solid transparent;border-bottom:5px solid transparent;border-left:5px solid rgba(0, 0, 0, 0.6)}.form-module .form{display:none;padding:40px}.form-module .form:first-child,.form-module .form:nth-child(2){display:block}.form-module h2{margin:0 0 20px;color:#0084b4;font-size:18px;font-weight:400;line-height:1}.form-module input{outline:none;display:block;width:100%;border:1px solid #d9d9d9;margin:0 0 20px;padding:10px 15px;box-sizing:border-box;font-weight:400;-webkit-transition:.3s ease;transition:.3s ease}.form-module .checkbox{display:block;margin:0 0 20px;padding:0 10px;font-weight:300;-webkit-transition:.3s ease;transition:.3s ease}.form-module .checkbox > label{cursor:pointer}.form-module .checkbox > label > input[type=checkbox]{display:initial;width:auto;margin:0;margin-top:1px\9;line-height:normal}.form-module input:focus{border:1px solid #0084b4;color:#333}.form-module button{cursor:pointer;background:#0084b4;width:100%;border:0;padding:10px 15px;color:#fff;-webkit-transition:.3s ease;transition:.3s ease}.form-module button:hover{background:#178ab4}.form-module .cta{background:#f2f2f2;width:100%;padding:15px 40px;box-sizing:border-box;color:#666;font-size:12px;text-align:center}.form-module .cta a{color:#333;text-decoration:none}.footer{text-align:center;letter-spacing:2px}.footer span{font-size:12px}.footer span .fa{color:#0084b4}.footer span a{color:#0084b4;font-weight:600;text-decoration:none}</style>

<title>Login - <?php echo $_Oli->getSetting('name'); ?></title>

</head>
<body>

<div class="header">
	<h1><a href="<?php echo $_Oli->getUrlParam(0); ?>"><?php echo $_Oli->getSetting('name'); ?></a></h1>
	<span>Powered by Oli Framework</span>
</div>

<?php if(isset($resultCode)) { ?>
	<?php
	list($prefix, $message) = explode(':', $resultCode, 2);
	if($prefix == 'E') $type = 'message-error';
	?>
	
	<div class="message <?php echo $type; ?>">
		<div class="content">
			<?php echo $message; ?>
		</div>
	</div>
<?php } ?>

<div class="module form-module">
	<?php if($_Oli->getUrlParam(2) == 'recover' AND !$hideRecoverUI) { ?>
		<div class="form">
			<h2>Recover your account</h2>
			<form action="<?php echo $_Oli->getUrlParam(0); ?>form.php?callback=<?php echo urlencode($_Oli->getUrlParam(0) . 'login/recover'); ?>" method="post">
				<input type="email" name="email" value="<?php echo $_Oli->getPostVars('email'); ?>" placeholder="Email Address" />
				<button type="submit">Recover</button>
			</form>
		</div>
		<div class="cta"><a href="<?php echo $_Oli->getUrlParam(0); ?>form.php?callback=<?php echo urlencode($_Oli->getUrlParam(0) . 'login/'); ?>">Login to your account</a></div>
	<?php } else if($_Oli->getUrlParam(2) == 'change-password' AND !$hideChangePasswordUI AND $requestInfos = $_Oli->isExistAccountInfos('REQUESTS', array('activate_key' => $_Oli->getUrlParam(3)))) { ?>
		<div class="form">
			<h2>Change your pasword</h2>
			<form action="<?php echo $_Oli->getUrlParam(0); ?>form.php?callback=<?php echo urlencode($_Oli->getUrlParam(0) . 'login/change-password/' . $_Oli->getUrlParam(3)); ?>" method="post">
				<input type="text" name="username" value="<?php echo $requestInfos['username']; ?>" disabled />
				<input type="text" name="activateKey" value="<?php echo $_Oli->getUrlParam(3); ?>" disabled />
				<input type="password" name="newPassword" value="<?php echo $_Oli->getPostVars('newPassword'); ?>" placeholder="New Password" />
				<button type="submit">Change</button>
			</form>
		</div>
		<div class="cta"><a href="<?php echo $_Oli->getUrlParam(0); ?>login/">Login to your account</a></div>
	<?php } else { ?>
		<div class="toggle"><i class="fa fa-times <?php if($_Oli->getUrlParam(2) != 'register') { ?>fa-pencil<?php } ?>"></i>
			<div class="tooltip"><?php if($_Oli->getUrlParam(2) != 'register') { ?>Register<?php } else { ?>Login<?php } ?></div>
		</div>
		
		<div class="form" style="display: <?php if($_Oli->getUrlParam(2) == 'register') { ?>none<?php } else { ?>block<?php } ?>;">
			<h2>Login to your account</h2>
			<form action="<?php echo $_Oli->getUrlParam(0); ?>form.php?callback=<?php echo urlencode($_Oli->getUrlParam(0) . 'login/'); ?>" method="post">
				<?php if(!empty($_Oli->getPostVars('referer')) OR !empty($_SERVER['HTTP_REFERER'])) { ?>
					<input type="hidden" name="referer" value="<?php echo (!empty($_Oli->getPostVars('referer'))) ? $_Oli->getPostVars('referer') : $_SERVER['HTTP_REFERER']; ?>" />
				<?php } ?>
				
				<input type="text" name="username" value="<?php echo $_Oli->getPostVars('username'); ?>" placeholder="Username" />
				<input type="password" name="password" value="<?php echo $_Oli->getPostVars('password'); ?>" placeholder="Password" />
				<div class="checkbox"><label><input type="checkbox" name="rememberMe" <?php if(!$_Oli->issetPostVars('rememberMe') OR $_Oli->getPostVars('rememberMe')) { ?>checked<?php } ?> /> "Run clever boy, and remember me"</label></div>
				<button type="submit">Login</button>
			</form>
		</div>
		<div class="form" style="display: <?php if($_Oli->getUrlParam(2) != 'register') { ?>none<?php } else { ?>block<?php } ?>;">
			<h2>Create an account</h2>
			<form action="<?php echo $_Oli->getUrlParam(0); ?>form.php?callback=<?php echo urlencode($_Oli->getUrlParam(0) . 'login/register'); ?>" method="post">
				<input type="text" name="username" value="<?php echo $_Oli->getPostVars('username'); ?>" placeholder="Username" />
				<input type="password" name="password" value="<?php echo $_Oli->getPostVars('password'); ?>" placeholder="Password" />
				<input type="email" name="email" value="<?php echo $_Oli->getPostVars('email'); ?>" placeholder="Email" />
				<button type="submit">Register</button>
			</form>
		</div>
		
		<div class="cta"><a href="<?php echo $_Oli->getUrlParam(0); ?>login/recover">Forgot your password?</a></div>
	<?php } ?>
</div>

<?php /*<div class="footer">
	<span><i class="fa fa-paint-brush"></i> Template by Andy Tran</span> - <span><i class="fa fa-code"></i> by Matiboux</span>
</div>*/ ?>

<script src='http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js'></script>
<script>$(".toggle").click(function(){$(this).children("i").toggleClass("fa-pencil");if($(this).children(".tooltip").text() == "Register"){$(this).children(".tooltip").text("Login");}else{$(this).children(".tooltip").text("Register");}$(".form").animate({height:"toggle","padding-top":"toggle","padding-bottom":"toggle",opacity:"toggle"},"slow")});</script>

</body>
</html>