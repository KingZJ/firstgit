'use strict';

// 登录控制器
angular
.module('dachuwang')
.controller('loginCtrl', ['$scope', '$state', 'userAuthService', 'rpc', '$cookieStore', 'daChuLocal', function($scope, $state, userAuthService, rpc, $cookieStore, daChuLocal) {

  if(userAuthService.isLogined()) {
    rpc.redirect('/user/center');
    return false;
  }

  // 登录model
  $scope.user = {
    mobile   : '',
    password : ''
  };

  // 提示信息
  $scope.message = {
    text   : '',
    status : ''
  };

  // 登录操作
  $scope.login = function() {

    $scope.show_error = true;
    if($scope.loginForm.$invalid) {
      $scope.loginForm.submitted = true;

      $scope.isL = false;
      return false;
    }

    $scope.isL = true;
    rpc.load('user/login', 'POST', $scope.user).then(function(msg) {
      daChuLocal.set('token', msg.token);
      daChuLocal.set('role_id', msg.info.type);
      daChuLocal.set('site_id', msg.info.site_id);
      daChuLocal.set('city_id', msg.info.city_id);
      if(rpc.refer && rpc.refer != '/') {
        rpc.redirect(rpc.refer);
      } else {
        $state.go('page.homemanage'); 
      }
    },
    //failed
    function(msg) {
      $scope.error = {cls:'alert alert-danger', message : msg};
    });
  };
}]);
