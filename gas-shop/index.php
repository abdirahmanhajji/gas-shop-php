<?php
require 'config.php';
function q($sql,$a=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($a);return $s;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function m($n){return 'UGX '.number_format((int)$n);}
function go($p,$msg){header("Location: index.php?p=$p&msg=".urlencode($msg));exit;}
$BAL="s.qty*s.price-IFNULL((SELECT SUM(amount) FROM payments WHERE sale_id=s.id),0)";

if(($_GET['p']??'')==='logout'){session_destroy();header('Location: index.php');exit;}
if(empty($_SESSION['uid'])){
  $err='';
  if(($_POST['action']??'')==='login'){
    $u=q("SELECT * FROM users WHERE username=?",[trim($_POST['username']??'')])->fetch();
    if($u&&password_verify($_POST['password']??'',$u['password_hash'])){
      session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['user']=$u['username'];$_SESSION['role']=$u['role'];
      header('Location: index.php');exit;}
    $err='Wrong username or password';
  }
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#1c2530;font:15px "Segoe UI",system-ui,sans-serif}
form{background:#fff;padding:26px;border-radius:12px;width:min(340px,92vw)}h1{margin:0 0 16px;font-size:20px}label{display:block;font-size:13px;font-weight:600;margin:10px 0 4px}
input{width:100%;padding:10px;border:1px solid #dedbd3;border-radius:8px;font:inherit}button{margin-top:16px;width:100%;background:#e4570f;color:#fff;border:0;border-radius:8px;padding:11px;font:inherit;font-weight:700;cursor:pointer}
.e{background:#fbe3e0;color:#a52a1f;padding:8px 10px;border-radius:8px;font-size:14px}</style></head><body>
<form method="post"><h1>🔥 Gas Shop</h1><?php if($err):?><div class="e"><?=h($err)?></div><?php endif?>
<input type="hidden" name="action" value="login"><label for="u">Username</label><input id="u" name="username" autofocus required>
<label for="pw">Password</label><input id="pw" type="password" name="password" required><button>Sign in</button></form></body></html><?php exit;}
$admin=($_SESSION['role']??'')==='admin';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??''; $i=fn($k)=>max(0,(int)($_POST[$k]??0));
  if(in_array($a,['save_cyl','del_cyl','add_user','del_user','reset_pw'])&&!$admin)go('dash','Only the admin can do that');
  if($a==='chg_pw'){
    $u=q("SELECT * FROM users WHERE id=?",[$_SESSION['uid']])->fetch();
    if(!password_verify($_POST['old']??'',$u['password_hash']))go('acct','Current password is wrong');
    if(strlen($_POST['new']??'')<6)go('acct','New password must be at least 6 characters');
    q("UPDATE users SET password_hash=? WHERE id=?",[password_hash($_POST['new'],PASSWORD_DEFAULT),$u['id']]);go('acct','Password changed');
  }
  if($a==='add_user'){
    $un=trim($_POST['username']??'');
    if($un===''||strlen($_POST['password']??'')<6)go('acct','Enter a username and a password of at least 6 characters');
    if(q("SELECT COUNT(*) c FROM users WHERE username=?",[$un])->fetch()['c'])go('acct','That username is taken');
    q("INSERT INTO users (username,password_hash,role) VALUES (?,?,?)",[$un,password_hash($_POST['password'],PASSWORD_DEFAULT),($_POST['role']??'')==='admin'?'admin':'staff']);go('acct','User added');
  }
  if($a==='reset_pw'){
    if(strlen($_POST['password']??'')<6)go('acct','Password must be at least 6 characters');
    q("UPDATE users SET password_hash=? WHERE id=?",[password_hash($_POST['password'],PASSWORD_DEFAULT),$i('id')]);go('acct','Password reset');
  }
  if($a==='del_user'){
    if($i('id')==$_SESSION['uid'])go('acct','You cannot delete your own account');
    q("DELETE FROM users WHERE id=?",[$i('id')]);go('acct','User deleted');
  }
  if($a==='sell'){
    $cid=$i('cid');$qty=$i('qty');$price=$i('price');$paid=min($i('paid'),$qty*$price);$cust=trim($_POST['customer']??'');
    if($paid<$qty*$price && $cust==='') go('sell','Add the customer name for unpaid sales');
    $pdo->beginTransaction();
    $c=q("SELECT * FROM cylinders WHERE id=? FOR UPDATE",[$cid])->fetch();
    if(!$c||$qty<1||$qty>$c['full_qty']){$pdo->rollBack();go('sell','Not enough full cylinders in stock');}
    q("UPDATE cylinders SET full_qty=full_qty-?, empty_qty=empty_qty+? WHERE id=?",[$qty,isset($_POST['ret'])?$qty:0,$cid]);
    q("INSERT INTO sales (cylinder_id,customer,qty,price) VALUES (?,?,?,?)",[$cid,$cust?:'Walk-in',$qty,$price]);
    $sid=$pdo->lastInsertId();
    if($paid>0)q("INSERT INTO payments (sale_id,amount) VALUES (?,?)",[$sid,$paid]);
    $pdo->commit();go('dash','Sale saved');
  }
  if($a==='pay'){
    $r=q("SELECT $BAL AS b FROM sales s WHERE s.id=?",[$i('sid')])->fetch();
    $amt=min($i('amount'),(int)($r['b']??0));
    if($amt>0)q("INSERT INTO payments (sale_id,amount) VALUES (?,?)",[$i('sid'),$amt]);
    go('debts','Payment recorded');
  }
  if($a==='save_cyl'){
    $v=[trim($_POST['name']),$i('price'),$i('full'),$i('empty'),$i('low')];
    if($v[0]==='')go('cyl','Enter a name');
    if($i('id'))q("UPDATE cylinders SET name=?,price=?,full_qty=?,empty_qty=?,low_level=? WHERE id=?",[...$v,$i('id')]);
    else q("INSERT INTO cylinders (name,price,full_qty,empty_qty,low_level) VALUES (?,?,?,?,?)",$v);
    go('cyl','Saved');
  }
  if($a==='del_cyl'){
    if(q("SELECT COUNT(*) c FROM sales WHERE cylinder_id=?",[$i('id')])->fetch()['c']>0)go('cyl','Cannot delete: this cylinder has sales');
    q("DELETE FROM cylinders WHERE id=?",[$i('id')]);go('cyl','Deleted');
  }
  if($a==='refill'||$a==='recv'){
    $n=$i('n');$c=q("SELECT * FROM cylinders WHERE id=?",[$i('id')])->fetch();
    if($n<1)go('empty','Enter a number');
    if($a==='refill'){
      if($n>$c['empty_qty'])go('empty','Only '.$c['empty_qty'].' empty cylinders');
      q("UPDATE cylinders SET empty_qty=empty_qty-?,full_qty=full_qty+? WHERE id=?",[$n,$n,$c['id']]);go('empty','Refilled '.$n);
    }
    q("UPDATE cylinders SET empty_qty=empty_qty+? WHERE id=?",[$n,$c['id']]);go('empty','Empties added');
  }
}

$p=$_GET['p']??'dash';
$cyls=q("SELECT * FROM cylinders ORDER BY id")->fetchAll();
$owedCount=q("SELECT COUNT(*) c FROM sales s WHERE $BAL>0")->fetch()['c'];
$pages=['dash'=>'Dashboard','sell'=>'New sale','cyl'=>'Cylinders','empty'=>'Empty cylinders','debts'=>'Unpaid','hist'=>'Sales history','acct'=>'Account'];
if(!$admin)unset($pages['cyl']);
if(!isset($pages[$p]))$p='dash';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gas Shop</title>
<style>
:root{--bg:#f4f3ef;--card:#fff;--ink:#1c2530;--mute:#6b7683;--line:#dedbd3;--flame:#e4570f;--ok:#2a8a57;--bad:#c0392b;--warn:#d98a00;--side:#1c2530}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 "Segoe UI",system-ui,sans-serif;display:flex;min-height:100vh}
aside{width:220px;background:var(--side);color:#eceae4;padding:18px 0;flex:none;position:sticky;top:0;height:100vh}
aside h1{font-size:18px;margin:0 18px 16px}aside a{display:block;padding:11px 18px;color:#c9ced4;text-decoration:none;border-left:4px solid transparent}
aside a.on{background:#27323f;color:#fff;border-color:var(--flame)}aside a:hover{color:#fff}
.who{padding:14px 18px;color:#9aa4af;font-size:13px;border-top:1px solid #33404d;margin-top:10px}.who a{padding:6px 0 0;border:0;color:#fff;text-decoration:underline}
.badge{background:var(--bad);color:#fff;border-radius:99px;font-size:12px;padding:1px 8px;float:right}
main{flex:1;padding:20px;min-width:0;max-width:1000px}h2{margin:0 0 14px;font-size:20px}
.grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:12px}
.card h3{margin:0 0 4px;font-size:13px;color:var(--mute);font-weight:600}.big{font-size:25px;font-weight:800}.sub{font-size:13px;color:var(--mute)}
.ok{border-left:6px solid var(--ok)}.bad{border-left:6px solid var(--bad)}.low{border-left:6px solid var(--warn)}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:8px 6px;border-bottom:1px solid var(--line)}th{color:var(--mute);font-size:13px}.r{text-align:right}.tw{overflow-x:auto}
label{display:block;font-size:13px;font-weight:600;margin-bottom:4px}input,select{width:100%;padding:9px;border:1px solid var(--line);border-radius:8px;font:inherit;background:var(--bg);color:var(--ink)}
.f{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));align-items:end}
.btn{background:var(--flame);color:#fff;border:0;border-radius:8px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}.btn.g{background:#fff;color:var(--ink);border:1px solid var(--line)}.btn.d{background:var(--bad)}
.msg{background:var(--ink);color:#fff;padding:10px 14px;border-radius:8px;margin-bottom:14px}
@media(max-width:760px){body{display:block}aside{width:100%;height:auto;position:static;display:flex;overflow-x:auto;padding:0}aside h1{display:none}aside a{white-space:nowrap;border-left:0;border-bottom:4px solid transparent}aside a.on{border-color:var(--flame)}}
</style></head><body>
<aside><h1>🔥 Gas Shop</h1>
<?php foreach($pages as $k=>$l):?><a href="?p=<?=$k?>" class="<?=$p===$k?'on':''?>"><?=$l?><?php if($k==='debts'&&$owedCount):?><span class="badge"><?=$owedCount?></span><?php endif?></a><?php endforeach?>
<div class="who">Signed in as <b><?=h($_SESSION['user'])?></b> (<?=h($_SESSION['role'])?>)<a href="?p=logout">Sign out</a></div>
</aside><main>
<?php if(!empty($_GET['msg'])):?><div class="msg"><?=h($_GET['msg'])?></div><?php endif?>

<?php if($p==='dash'):
$sold=q("SELECT IFNULL(SUM(qty*price),0) t,COUNT(*) n FROM sales WHERE DATE(created_at)=CURDATE()")->fetch();
$cash=q("SELECT IFNULL(SUM(amount),0) t FROM payments WHERE DATE(created_at)=CURDATE()")->fetch()['t'];
$owed=q("SELECT IFNULL(SUM($BAL),0) t FROM sales s")->fetch()['t'];
$today=q("SELECT s.*,c.name,$BAL AS bal FROM sales s JOIN cylinders c ON c.id=s.cylinder_id WHERE DATE(s.created_at)=CURDATE() ORDER BY s.id DESC")->fetchAll();?>
<h2>Today</h2>
<div class="grid">
<div class="card ok"><h3>Money received today</h3><div class="big"><?=m($cash)?></div><div class="sub">Includes debts paid today</div></div>
<div class="card"><h3>Sales value today</h3><div class="big"><?=m($sold['t'])?></div><div class="sub"><?=$sold['n']?> sale(s)</div></div>
<div class="card bad"><h3>Not paid yet</h3><div class="big"><?=m($owed)?></div><div class="sub"><?=$owedCount?> unpaid sale(s)</div></div></div>
<div class="grid"><?php foreach($cyls as $c):?>
<div class="card <?=$c['full_qty']<=$c['low_level']?'low':''?>"><h3><?=h($c['name'])?></h3><div class="big"><?=$c['full_qty']?> full</div>
<div class="sub"><?=$c['empty_qty']?> empty<?=$c['full_qty']<=$c['low_level']?' · Low stock, reorder soon':''?></div></div><?php endforeach?></div>
<div class="card tw"><h3>Today's sales</h3>
<?php if(!$today):?><p class="sub">No sales yet today.</p><?php else:?><table><tr><th>Time</th><th>Customer</th><th>Item</th><th class="r">Amount</th><th class="r">Status</th></tr>
<?php foreach($today as $s):?><tr><td><?=date('H:i',strtotime($s['created_at']))?></td><td><?=h($s['customer'])?></td><td><?=h($s['name'])?> ×<?=$s['qty']?></td><td class="r"><?=m($s['qty']*$s['price'])?></td><td class="r"><?=$s['bal']>0?'<b style="color:var(--bad)">owes '.m($s['bal']).'</b>':'paid'?></td></tr><?php endforeach?></table><?php endif?></div>

<?php elseif($p==='sell'):?>
<h2>New sale</h2><form method="post" class="card"><input type="hidden" name="action" value="sell">
<div class="f">
<div><label>Customer name</label><input name="customer" placeholder="e.g. Mama Sarah"></div>
<div><label>Cylinder</label><select name="cid" id="cid"><?php foreach($cyls as $c):?><option value="<?=$c['id']?>" data-price="<?=$c['price']?>"><?=h($c['name'])?> (<?=$c['full_qty']?> in stock)</option><?php endforeach?></select></div>
<div><label>Quantity</label><input type="number" name="qty" id="qty" min="1" value="1"></div>
<div><label>Price each</label><input type="number" name="price" id="price" min="0"></div>
<div><label>Paid now</label><input type="number" name="paid" id="paid" min="0"></div>
<div><button class="btn">Save sale</button></div></div>
<p class="sub" id="sum"></p>
<label><input type="checkbox" name="ret" checked style="width:auto"> Customer returned an empty cylinder</label></form>
<script>
const $=id=>document.getElementById(id);let manual=false;
function upd(full){const o=$('cid').selectedOptions[0];if(full)$('price').value=o.dataset.price;if(!manual)$('paid').value=$('qty').value*$('price').value;
const t=$('qty').value*$('price').value,b=t-($('paid').value||0);$('sum').textContent='Total UGX '+t.toLocaleString()+(b>0?' · balance UGX '+b.toLocaleString()+' goes to Unpaid':' · fully paid');}
$('cid').onchange=()=>upd(1);$('qty').oninput=()=>upd();$('price').oninput=()=>upd();$('paid').oninput=()=>{manual=true;upd()};upd(1);
</script>

<?php elseif($p==='cyl'):?>
<h2>Cylinders</h2><p class="sub">Edit the name, price, stock and low-stock warning, then press Save.</p>
<?php foreach($cyls as $c):?><form method="post" class="card"><input type="hidden" name="action" value="save_cyl"><input type="hidden" name="id" value="<?=$c['id']?>">
<div class="f"><div><label>Name</label><input name="name" value="<?=h($c['name'])?>"></div>
<div><label>Price (UGX)</label><input type="number" name="price" value="<?=$c['price']?>"></div>
<div><label>Full</label><input type="number" name="full" value="<?=$c['full_qty']?>"></div>
<div><label>Empty</label><input type="number" name="empty" value="<?=$c['empty_qty']?>"></div>
<div><label>Warn below</label><input type="number" name="low" value="<?=$c['low_level']?>"></div>
<div><button class="btn">Save</button></div>
<div><button class="btn d" name="action" value="del_cyl" onclick="return confirm('Delete this cylinder?')">Delete</button></div></div></form><?php endforeach?>
<form method="post" class="card"><input type="hidden" name="action" value="save_cyl"><h3>Add a new cylinder size</h3>
<div class="f"><div><label>Name</label><input name="name" placeholder="e.g. Medium (9 kg)"></div><div><label>Price</label><input type="number" name="price" value="0"></div>
<div><label>Full</label><input type="number" name="full" value="0"></div><div><label>Empty</label><input type="number" name="empty" value="0"></div>
<div><label>Warn below</label><input type="number" name="low" value="5"></div><div><button class="btn g">Add cylinder</button></div></div></form>

<?php elseif($p==='empty'):?>
<h2>Empty cylinders</h2><p class="sub">Empties come back from customers. Refill them to turn them into full stock.</p>
<?php foreach($cyls as $c):?><div class="card"><h3><?=h($c['name'])?></h3><div class="big"><?=$c['empty_qty']?> empty</div><div class="sub"><?=$c['full_qty']?> full</div>
<div class="f" style="margin-top:10px">
<form method="post" class="f" style="grid-column:span 2"><input type="hidden" name="action" value="refill"><input type="hidden" name="id" value="<?=$c['id']?>"><div><label>Refill how many?</label><input type="number" name="n" min="1"></div><div><button class="btn">Refill to full</button></div></form>
<form method="post" class="f" style="grid-column:span 2"><input type="hidden" name="action" value="recv"><input type="hidden" name="id" value="<?=$c['id']?>"><div><label>Empties received</label><input type="number" name="n" min="1"></div><div><button class="btn g">Add empties</button></div></form></div></div><?php endforeach?>

<?php elseif($p==='debts'):
$d=q("SELECT s.*,c.name,$BAL AS bal FROM sales s JOIN cylinders c ON c.id=s.cylinder_id HAVING bal>0 ORDER BY s.created_at")->fetchAll();?>
<h2>Unpaid</h2><div class="card bad"><h3>Total owed</h3><div class="big"><?=m(array_sum(array_column($d,'bal')))?></div></div>
<div class="card tw"><?php if(!$d):?><p class="sub">Nobody owes you money.</p><?php else:?><table><tr><th>Customer</th><th>Date</th><th>Item</th><th class="r">Balance</th><th>Record payment</th></tr>
<?php foreach($d as $s):?><tr><td><?=h($s['customer'])?></td><td><?=date('d M',strtotime($s['created_at']))?></td><td><?=h($s['name'])?> ×<?=$s['qty']?></td><td class="r"><b style="color:var(--bad)"><?=m($s['bal'])?></b></td>
<td><form method="post" style="display:flex;gap:6px"><input type="hidden" name="action" value="pay"><input type="hidden" name="sid" value="<?=$s['id']?>"><input type="number" name="amount" min="1" max="<?=$s['bal']?>" placeholder="Amount" style="width:110px"><button class="btn">Pay</button></form></td></tr><?php endforeach?></table><?php endif?></div>

<?php elseif($p==='acct'):?>
<h2>Account</h2>
<form method="post" class="card"><input type="hidden" name="action" value="chg_pw"><h3>Change my password</h3>
<div class="f"><div><label>Current password</label><input type="password" name="old" required></div>
<div><label>New password (6+ characters)</label><input type="password" name="new" required></div><div><button class="btn">Change password</button></div></div></form>
<?php if($admin):$us=q("SELECT * FROM users ORDER BY id")->fetchAll();?>
<form method="post" class="card"><input type="hidden" name="action" value="add_user"><h3>Add a user</h3>
<div class="f"><div><label>Username</label><input name="username" required></div><div><label>Password</label><input type="password" name="password" required></div>
<div><label>Role</label><select name="role"><option value="staff">Staff (sell, empties, unpaid)</option><option value="admin">Admin (everything)</option></select></div><div><button class="btn">Add user</button></div></div></form>
<div class="card tw"><h3>Users</h3><table><tr><th>Username</th><th>Role</th><th>Reset password</th><th></th></tr>
<?php foreach($us as $u):?><tr><td><?=h($u['username'])?></td><td><?=h($u['role'])?></td>
<td><form method="post" style="display:flex;gap:6px"><input type="hidden" name="action" value="reset_pw"><input type="hidden" name="id" value="<?=$u['id']?>"><input type="password" name="password" placeholder="New password" style="width:150px"><button class="btn g">Reset</button></form></td>
<td><?php if($u['id']!=$_SESSION['uid']):?><form method="post"><input type="hidden" name="action" value="del_user"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn d" onclick="return confirm('Delete this user?')">Delete</button></form><?php endif?></td></tr><?php endforeach?></table></div>
<?php endif?>

<?php else:
$d=q("SELECT s.*,c.name,$BAL AS bal FROM sales s JOIN cylinders c ON c.id=s.cylinder_id ORDER BY s.id DESC LIMIT 100")->fetchAll();?>
<h2>Sales history</h2><div class="card tw"><table><tr><th>Date</th><th>Customer</th><th>Item</th><th class="r">Total</th><th class="r">Balance</th></tr>
<?php foreach($d as $s):?><tr><td><?=date('d M H:i',strtotime($s['created_at']))?></td><td><?=h($s['customer'])?></td><td><?=h($s['name'])?> ×<?=$s['qty']?></td><td class="r"><?=m($s['qty']*$s['price'])?></td><td class="r"><?=$s['bal']>0?m($s['bal']):'paid'?></td></tr><?php endforeach?></table></div>
<?php endif?>
</main></body></html>
