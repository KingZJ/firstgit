<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>BI决策支持系统</title>

    <!-- Bootstrap core CSS -->
    <link href="http://cdn.bootcss.com/bootstrap/3.3.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?php echo APPPATH ?>views/shared/css/login.css" rel="stylesheet">
  </head>

  <body>

    <h1 class="text-center">BI决策支持系统</h1>
    <form class="form-signin" action="<?php echo $base_url;?>/user/login" type="post">
      <h2 class="form-signin-heading">请登录</h2>
      <div class="alert alert-danger hide" id="wrong" role="alert"></div>
      <label for="inputText" class="sr-only">手机号</label>
      <input type="text" id="inputText" class="form-control" name="mobile" placeholder="手机号" required autofocus >
      <label for="inputPassword" class="sr-only">密码</label>
      <input type="password" id="inputPassword" class="form-control" name="password" placeholder="密码" required >
      <div class="checkbox">
        <label>
          <input type="checkbox" name="remember-me"  value="remember-me">记住一周 
        </label>
      </div>

      <button id="submit"  class="btn btn-lg btn-primary btn-block" type="button" >登录</button>
    </form>

    <script src="http://cdn.bootcss.com/jquery/1.11.2/jquery.min.js"></script>
    <script src="http://cdn.bootcss.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <script type="text/javascript">
        var base_url = "<?php echo $base_url; ?>";
        $(function(){
            $("#submit").click(function(){
                if($("input[name=mobile]").val() == '' || $("input[name=password]").val() == ''){
                    $("#wrong").html('请输入手机号或密码');
                    $("#wrong").removeClass("hide");
                    return false;
                }else{
                    $.ajax({
                        'url' : base_url+'/user/login',
                        'type' : "post",
                        'dataType' : 'json',
                        'data' : {
                            'mobile' : $("input[name=mobile]").val(),
                            'password' : $("input[name=password]").val(),
                            'remember-me' : $("input[name=remember-me]").val()
                        },
                        'success' : function(data){
                            if(data && data.status == 0){
                                location = base_url;
                            }else{
                                $("#wrong").html(data.msg);
                                $("#wrong").removeClass("hide");
                            }
                        }
                    });
                }
            });
        });
    </script>
  </body>
</html>
