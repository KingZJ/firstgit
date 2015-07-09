'use strict';

angular.module('dachuwang')
  .controller('CdetailNavController',['$scope','$state', function($scope, $state) {
    $scope.showType = 0;
    $scope.statusopen = true;
    $scope.func = {
      historyGo : function() {
        $state.go('page.customerdetail.cdetailhistory');
      }
    }
    $scope.newmsg = $scope.historymsg = $scope.basemsg = true;
    $scope.setStatus = function(num) {
      $scope.showType = num;
    }
    $scope.tabs = [{status:0,href:'page.customerdetail',name:'客户信息'},{status:1,href:'page.customerdetail.trends',name:'客户动态'}];
    $scope.userinfo = {newmsg:'最新短信',history:'历史信息',baseinfo:'基本信息'}
    
}]);
