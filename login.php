<?php
/*\
|*|  ----------------------------
|*|  --- [  Oli Login page  ] ---
|*|  ----------------------------
|*|  Developed for Oli Beta 1.8.0 (development branch)
|*|  
|*|  The official login page for Oli, an open source PHP Framework made by Matiboux.
|*|  Created and developed by Mathieu Guérin – aka Matiboux.
|*|  
|*|  Oli Login page Github repository: https://github.com/OliFramework/Oli-Login-Page
|*|  Original CSS template by Andy Tran: http://codepen.io/andytran/pen/PwoQgO
|*|  Oli Github repository: https://github.com/OliFramework/Oli/
|*|  
|*|  Once the framework is properly setup and configured, just place this file in the website theme directory.
|*|  If your website url is 'urwebs.it' and the login page is kept in 'content/theme/' as 'login.php', you should be able to open it from 'urwebs.it/login/'
|*|  
|*|  --- --- ---
|*|  
|*|  Changelog: refer to repository commits
|*|  
|*|  Stuff to do next:
|*|  - Ban both IP and browser (cookie) of users who fails login too many times to prevent brute-force attacks.
|*|  - Allow direct switch between the recover and the change-password form, the same way as the login/register switch.
|*|  
|*|  Stuff to do on Oli:
|*|  - Add config for disabling login management feature (such as register).
|*|  - Add support and config for login limits.
|*|  - Add config for login duration.
|*|  
|*|  --- --- ---
|*|  
|*|  MIT License
|*|  
|*|  Copyright (c) 2015-2017 Matiboux (Mathieu Guérin)
|*|  
|*|    Permission is hereby granted, free of charge, to any person obtaining a copy
|*|    of this software and associated documentation files (the "Software"), to deal
|*|    in the Software without restriction, including without limitation the rights
|*|    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
|*|    copies of the Software, and to permit persons to whom the Software is
|*|    furnished to do so, subject to the following conditions:
|*|    
|*|    The above copyright notice and this permission notice shall be included in all
|*|    copies or substantial portions of the Software.
|*|    
|*|    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
|*|    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
|*|    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
|*|    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
|*|    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
|*|    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
|*|    SOFTWARE.
\*/

/** Login management is disabled by the current config */
if(!$_Oli->getAccountsManagementStatus()) header('Location: ' . $_Oli->getUrlParam(0));

$mailHeaders = 'From: Noreply ' . $_Oli->getSetting('name') . ' <noreply@' . $_Oli->getUrlParam('domain') . '>' . "\r\n";
$mailHeaders .= 'MIME-Version: 1.0' . "\r\n";
$mailHeaders .= 'Content-type: text/html; charset=utf-8' . "\r\n";

if($_Oli->issetPostVars() AND $_Oli->getUrlParam(2) == 'change-password' AND !empty($_Oli->getPostVars('activateKey'))) {
	if($_Oli->isEmptyPostVars('newPassword')) $resultCode = 'E:Please enter the new password you want to set';
	else if(!$requestInfos = $_Oli->getAccountLines('REQUESTS', array('activate_key' => hash('sha512', $_Oli->getPostVars('activateKey'))))) $resultCode = 'E:Sorry, the request you asked for does not exist';
	else if($requestInfos['action'] != 'change-password') $resultCode = 'E:The request you triggered does not allow you to change your password';
	else if(time() > strtotime($requestInfos['expire_date'])) $resultCode = 'E:Sorry, the request you triggered has expired';
	else {
		/** Logout the user if they're logged in */
		if(!$_Oli->verifyAuthKey()) $_Oli->logoutAccount();
		
		/** Deletes all the user sessions, change the user password and deletes the request */
		if($_Oli->deleteAccountLines('SESSIONS', $requestInfos['username']) AND $_Oli->updateAccountInfos('ACCOUNTS', array('password' => $_Oli->hashPassword($_Oli->getPostVars('newPassword'))), $requestInfos['username']) AND $_Oli->deleteAccountLines('REQUESTS', array('activate_key' => hash('sha512', $_Oli->getPostVars('activateKey'))))) {
			$hideChangePasswordUI = true;
			$resultCode = 'S:Your password has been successfully changed!';
		} else $resultCode = 'S:An error occurred while changing your password';
	}
}
else if($_Oli->verifyAuthKey()) {
	if($_Oli->getUrlParam(2) == 'logout') {
		if($_Oli->logoutAccount()) $resultCode = 'S:You have been successfully disconnected';
		else $resultCode = 'E:An error occurred while disconnecting you';
	} else header('Location: ' . $_Oli->getUrlParam(0));	
}
/** At this point, the user cannot be logged in */
else if($_Oli->getUrlParam(2) == 'activate' AND !empty($_Oli->getUrlParam(3))) {
	if(!$requestInfos = $_Oli->getAccountLines('REQUESTS', array('activate_key' => hash('sha512', $_Oli->getUrlParam(3))))) $resultCode = 'E:Sorry, the request you asked for does not exist';
	else if($requestInfos['action'] != 'activate') $resultCode = 'E:The request you triggered does not allow you to activate any account';
	else if(time() > strtotime($requestInfos['expire_date'])) $resultCode = 'E:Sorry, the request you triggered has expired';
	else if($_Oli->deleteAccountLines('REQUESTS', array('activate_key' => hash('sha512', $_Oli->getUrlParam(3)))) AND $_Oli->updateUserRight('USER', $requestInfos['username'])) $resultCode = 'S:Your account has been successfully activated!';
	else $resultCode = 'E:An error occurred while activating your account';
}
else if($_Oli->issetPostVars()) {
	if($_Oli->getUrlParam(2) == 'recover') {
		if($_Oli->isEmptyPostVars('email')) $resultCode = 'E:Please enter your email';
		else if(!$username = $_Oli->getAccountInfos('ACCOUNTS', 'username', array('email' => trim($_Oli->getPostVars('email'))), false)) $resultCode = 'E:Sorry, no account is associated with the email you entered';
		else if($requestInfos = $_Oli->getAccountLines('REQUESTS', array('username' => $username, 'action' => 'change-password')) AND time() <= strtotime($requestInfos['expire_date'])) $resultCode = 'E:Sorry, a change-password request already exists for that account, please check your mail inbox.';
		else if($activateKey = $_Oli->createRequest($username, 'change-password')) {
			$email = $_Oli->getPostVars('email');
			$subject = 'One more step to change your password';
			/** This message will need to be reviewed in a future release */
			$message = nl2br('Hi ' . $username . '!
A change-password request has been created for your account.
To set your new password, you just need to click on <a href="' . $_Oli->getShortcutLink('login') . 'change-password/' . $activateKey . '">this link</a> and follow the instructions.
This request will stay valid for ' . $expireDelay = $_Oli->getRequestsExpireDelay() /3600 /24 . ' ' . ($expireDelay > 1 ? 'days' : 'day') . '. Once it has expired, the link will be desactivated.

If you can\'t open the link, just copy it in your browser: ' . $_Oli->getUrlParam(0)  . $_Oli->getUrlParam(1) . '/change-password/' . $activateKey . '.

If you didn\'t want to change your password or didn\'t ask for this request, please just ignore this mail.
Also  , if possible, please take time to cancel the request from your account settings.');
			
			if(mail($email, $subject, $message, $mailHeaders)) {
				$hideRecoverUI = true;
				$resultCode = 'S:The request has been successfully created and a mail has been sent to you';
			} else {
				$_Oli->deleteAccountLines('REQUESTS', array('activate_key' => $activateKey));
				$resultCode = 'D:An error occurred while sending the mail to you';
			}
		} else $resultCode = 'E:An error occurred while creating the change-password request';
	}
	else if($_Oli->config['allow_register'] AND $_Oli->issetPostVars('email')) {
		if($_Oli->isEmptyPostVars('username')) $resultCode = 'E:Please enter an username';
		else {
			$username = trim($_Oli->getPostVars('username'));
			if($_Oli->isExistAccountInfos('ACCOUNTS', $username, false)) $resultCode = 'E:Sorry, the username you choose is already associated with an existing account';
			else if($_Oli->isProhibitedUsername($username)) $resultCode = 'E:Sorry, the username you choose is prohibited';
			else if($_Oli->isEmptyPostVars('password')) $resultCode = 'E:Please enter an password';
			else if($_Oli->isEmptyPostVars('email')) $resultCode = 'E:Please enter your email';
			else {
				$email = strtolower(trim($_Oli->getPostVars('email')));
				if($_Oli->isExistAccountInfos('ACCOUNTS', array('email' => $email), false)) $resultCode = 'E:Sorry, the email you entered is already associated with an existing account';
				else if($_Oli->registerAccount($username, $_Oli->getPostVars('password'), $email, array('headers' => $mailHeaders))) {
					if($_Oli->config['account_activation']) $resultCode = 'S:Your account has been successfully created and a mail has been sent to you';
					else $resultCode = 'S:Your account has been successfully created; you can now login to it';
				} else $resultCode = 'E:An error occurred while creating your account';
			}
		}
	} else {
		if($_Oli->isEmptyPostVars('username')) $resultCode = 'E:Please enter your username or your email';
		else {
			$username = trim($_Oli->getPostVars('username'));
			$isExistByUsername = $_Oli->isExistAccountInfos('ACCOUNTS', $username, false);
			$isExistByEmail = $_Oli->isExistAccountInfos('ACCOUNTS', array('email' => $username), false);
			if(!$isExistByUsername AND !$isExistByEmail) $resultCode = 'E:Sorry, no account is associated with the username or email you entered';
			else if(($isExistByUsername AND $_Oli->getUserRightLevel($username, false) == $_Oli->translateUserRight('NEW-USER')) OR ($isExistByEmail AND $_Oli->getUserRightLevel(array('email' => $username), false) == $_Oli->translateUserRight('NEW-USER'))) $resultCode = 'E:Sorry, the account associated with that username or email is not yet activated';
			else if(($isExistByUsername AND $_Oli->getUserRightLevel($username, false) == $_Oli->translateUserRight('BANNED')) OR ($isExistByEmail AND $_Oli->getUserRightLevel(array('email' => $username), false) == $_Oli->translateUserRight('BANNED'))) $resultCode = 'E:Sorry, the account associated with that username or email is banned and is not allowed to log in';
			else if(($isExistByUsername AND $_Oli->getUserRightLevel($username, false) < $_Oli->translateUserRight('USER')) OR ($isExistByEmail AND $_Oli->getUserRightLevel(array('email' => $username), false) < $_Oli->translateUserRight('USER'))) $resultCode = 'E:Sorry, the account associated with that username or email is not allowed to log in';
			else if($_Oli->isEmptyPostVars('password')) $resultCode = 'E:Please enter your password';
			else if($_Oli->verifyLogin($username, $_Oli->getPostVars('password'))) {
				$loginDuration = $_Oli->getPostVars('rememberMe') ? $_Oli->config['extended_session_duration'] : $_Oli->config['default_session_duration'];
				if($_Oli->loginAccount($username, $_Oli->getPostVars('password'), $loginDuration)) {
					if(!empty($_Oli->getPostVars('referer'))) header('Location: ' . $_Oli->getPostVars('referer'));
					else header('Location: ' . $_Oli->getUrlParam(0));
				} else $resultCode = 'E:An error occurred while logging you in';
			} else $resultCode = 'E:Sorry, the password you entered seems to be wrong';
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="<?=$_Oli->getSetting('name')?> login page" />
<meta name="keywords" content="oli,login,page,PHP,framework,official,Mathieu,Guérin,Mati,Matiboux" />
<meta name="author" content="Matiboux" />
<title>Login - <?php echo $_Oli->getSetting('name'); ?></title>

<style>html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,cite,code,del,dfn,em,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,b,u,i,center,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td,article,aside,canvas,details,embed,figure,figcaption,footer,header,hgroup,menu,nav,output,ruby,section,summary,time,mark,audio,video{margin:0;padding:0;border:0;font-size:100%;font:inherit;vertical-align:baseline}article,aside,details,figcaption,figure,footer,header,hgroup,menu,nav,section{display:block}body{line-height:1}ol,ul{list-style:none}blockquote,q{quotes:none}blockquote:before,blockquote:after,q:before,q:after{content:'';content:none}table{border-collapse:collapse;border-spacing:0}</style>
<link rel="stylesheet prefetch" href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,700" />
<?php $_Oli->loadStyle('https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', true); ?>
<style>body{background:#e9e9e9;color:#666;font-family:'Roboto', sans-serif;font-size:14px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.header{padding:50px 0;text-align:center;letter-spacing:2px}.header h1{margin:0 0 20px;font-size:48px;font-weight:400}@media(max-width: 420px){.header h1{font-size:36px;}}.header h1 a{color:#0084b4;font-weight:400;text-decoration:none}.header span{font-size:14px}@media(max-width: 420px){.header span{font-size:12px;}}.header span .fa{color:#0084b4}.header span a{color:#0084b4;font-weight:600;text-decoration:none}.message,.form-module{position:relative;background:#fff;max-width:320px;width:100%;margin:0 auto 30px;border-top:5px solid #0084b4;box-shadow:0 0 3px rgba(0, 0, 0, 0.1)}.message.message-error{border-top:5px solid #d9534f}.message .content{padding:20px 40px}.message h2{color:#555;font-size:16px;font-weight:400;line-height:1}.form-module .toggle{cursor:pointer;position:absolute;top:-0;right:-0;background:#0084b4;width:30px;height:30px;margin:-5px 0 0;color:#fff;font-size:12px;line-height:30px;text-align:center}.form-module .toggle .tooltip{position:absolute;top:8px;right:40px;display:block;background:rgba(0, 0, 0, 0.6);width:auto;padding:5px;font-size:10px;line-height:1;text-transform:uppercase}.form-module .toggle .tooltip:before{content:'';position:absolute;top:5px;right:-5px;display:block;border-top:5px solid transparent;border-bottom:5px solid transparent;border-left:5px solid rgba(0, 0, 0, 0.6)}.form-module .form{display:none;padding:40px}.form-module .form:first-child,.form-module .form:nth-child(2){display:block}.form-module h2{margin:0 0 20px;color:#0084b4;font-size:18px;font-weight:400;line-height:1}.form-module input{outline:none;display:block;width:100%;border:1px solid #d9d9d9;margin:0 0 20px;padding:10px 15px;box-sizing:border-box;font-weight:400;-webkit-transition:.3s ease;transition:.3s ease}.form-module .checkbox{display:block;margin:0 0 20px;padding:0 10px;font-weight:300;-webkit-transition:.3s ease;transition:.3s ease}.form-module .checkbox > label{cursor:pointer}.form-module .checkbox > label > input[type=checkbox]{display:initial;width:auto;margin:0;margin-top:1px\9;line-height:normal}.form-module input:focus{border:1px solid #0084b4;color:#333}.form-module button{cursor:pointer;background:#0084b4;width:100%;border:0;padding:10px 15px;color:#fff;-webkit-transition:.3s ease;transition:.3s ease}.form-module button:hover{background:#178ab4}.form-module .cta{background:#f2f2f2;width:100%;padding:15px 40px;box-sizing:border-box;color:#666;font-size:12px;text-align:center}.form-module .cta a{color:#333;text-decoration:none}.footer{text-align:center;letter-spacing:2px}.footer span{font-size:12px}.footer span .fa{color:#0084b4}.footer span a{color:#0084b4;font-weight:600;text-decoration:none}</style>

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
		<div class="content"><?php echo $message; ?></div>
	</div>
<?php } ?>

<div class="module form-module">
	<?php /** Pre-edits for the recover/change-password switch */ ?>
	<?php //if(($_Oli->getUrlParam(2) == 'recover' AND !$hideRecoverUI) OR ($_Oli->getUrlParam(2) == 'change-password' AND !$hideChangePasswordUI)) { ?>
	<?php if($_Oli->getUrlParam(2) == 'recover' AND !$hideRecoverUI) { ?>
		<?php /*<div class="toggle"><i class="fa fa-times <?php if($_Oli->getUrlParam(2) != 'register') { ?>fa-pencil<?php } ?>"></i>
			<div class="tooltip"><?php if($_Oli->getUrlParam(2) != 'register') { ?>Register<?php } else { ?>Login<?php } ?></div>
		</div>*/ ?>
		
		<div class="form">
			<h2>Recover your account</h2>
			<form action="<?=$_Oli->getUrlParam(0)?>form.php?callback=<?=urlencode($_Oli->getUrlParam(0) . $_Oli->getUrlParam(1) . '/recover')?>" method="post">
				<input type="email" name="email" value="<?=$_Oli->getPostVars('email')?>" placeholder="Email address" />
				<button type="submit">Recover</button>
			</form>
		</div>
		<div class="cta"><a href="<?=$_Oli->getUrlParam(0) . $_Oli->getUrlParam(1)?>/">Login to your account</a></div>
	<?php } else if($_Oli->getUrlParam(2) == 'change-password' AND !$hideChangePasswordUI) { ?>
		<div class="form">
			<h2>Change your pasword</h2>
			<form action="<?=$_Oli->getUrlParam(0)?>form.php?callback=<?=urlencode($_Oli->getUrlParam(0) . $_Oli->getUrlParam(1) . '/change-password')?><?php if($requestInfos = $_Oli->getAccountLines('REQUESTS', array('activate_key' => hash('sha512', $_Oli->getUrlParam(3) ?: $_Oli->getPostVars('activateKey'))))) { ?>&activateKey=<?=urlencode($_Oli->getUrlParam(3) ?: $_Oli->getPostVars('activateKey'))?><?php } ?>" method="post">
				<?php if($requestInfos) { ?><input type="text" name="username" value="<?=$requestInfos['username']?>" placeholder="Username" disabled /><?php } ?>
				<input type="text" name="activateKey" value="<?=$_Oli->getUrlParam(3) ?: $_Oli->getPostVars('activateKey')?>" placeholder="Activation key" <?php if($requestInfos) { ?>disabled<?php } ?> />
				<input type="password" name="newPassword" value="<?=$_Oli->getPostVars('newPassword')?>" placeholder="New password" />
				<button type="submit">Update</button>
			</form>
		</div>
		<div class="cta"><a href="<?=$_Oli->getUrlParam(0) . $_Oli->getUrlParam(1)?>/">Login to your account</a></div>
	<?php } else { ?>
		<?php if($_Oli->config['allow_register']) { ?>
			<div class="toggle"><i class="fa fa-times <?php if($_Oli->getUrlParam(2) != 'register') { ?>fa-pencil<?php } ?>"></i>
				<div class="tooltip"><?php if($_Oli->getUrlParam(2) != 'register') { ?>Register<?php } else { ?>Login<?php } ?></div>
			</div>
		<?php } ?>
		
		<div class="form" style="display:<?php if($_Oli->getUrlParam(2) == 'register' AND $_Oli->config['allow_register']) { ?>none<?php } else { ?>block<?php } ?>">
			<h2>Login to your account</h2>
			<form action="<?=$_Oli->getUrlParam(0)?>form.php?callback=<?=urlencode($_Oli->getUrlParam(0) . $_Oli->getUrlParam(1) . '/')?>" method="post">
				<?php if(!empty($_Oli->getPostVars('referer')) OR !empty($_SERVER['HTTP_REFERER'])) { ?>
					<input type="hidden" name="referer" value="<?=!empty($_Oli->getPostVars('referer')) ? $_Oli->getPostVars('referer') : $_SERVER['HTTP_REFERER']?>" />
				<?php } ?>
				
				<input type="text" name="username" value="<?=$_Oli->getPostVars('username')?>" placeholder="Username" />
				<input type="password" name="password" value="<?=$_Oli->getPostVars('password')?>" placeholder="Password" />
				<div class="checkbox"><label><input type="checkbox" name="rememberMe" <?php if(!$_Oli->issetPostVars('rememberMe') OR $_Oli->getPostVars('rememberMe')) { ?>checked<?php } ?> /> « Run clever boy, and remember me »</label></div>
				<button type="submit">Login</button>
			</form>
		</div>
		<?php if($_Oli->config['allow_register']) { ?>
			<div class="form" style="display: <?php if($_Oli->getUrlParam(2) != 'register') { ?>none<?php } else { ?>block<?php } ?>;">
				<h2>Create an account</h2>
				<form action="<?=$_Oli->getUrlParam(0)?>form.php?callback=<?=urlencode($_Oli->getUrlParam(0) . $_Oli->getUrlParam(1) . '/register')?>" method="post">
					<input type="text" name="username" value="<?=$_Oli->getPostVars('username')?>" placeholder="Username" />
					<input type="password" name="password" value="<?=$_Oli->getPostVars('password')?>" placeholder="Password" />
					<input type="email" name="email" value="<?=$_Oli->getPostVars('email')?>" placeholder="Email address" />
					<button type="submit">Register</button>
				</form>
			</div>
		<?php } ?>
		<div class="cta"><a href="<?=$_Oli->getUrlParam(0) . $_Oli->getUrlParam(1)?>/recover">Forgot your password?</a></div>
	<?php } ?>
</div>

<div class="footer">
	<span><i class="fa fa-code"></i> by Matiboux</span> - <span><i class="fa fa-paint-brush"></i> by Andy Tran</span>
</div>

<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js'></script>
<script>$(".toggle").click(function(){$(this).children("i").toggleClass("fa-pencil");if($(this).children(".tooltip").text() == "Register"){$(this).children(".tooltip").text("Login");}else{$(this).children(".tooltip").text("Register");}$(".form").animate({height:"toggle","padding-top":"toggle","padding-bottom":"toggle",opacity:"toggle"},"slow")});</script>

</body>
</html>