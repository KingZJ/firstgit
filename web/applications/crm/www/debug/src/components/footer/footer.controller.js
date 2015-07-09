'use strict';

angular
  .module('dachuwang')
  .controller('footerCtrl', ['$scope', '$state', 'daChuLocal', '$cookieStore', function($scope, $state, daChuLocal,$cookieStore) {
    var tabList = [
      {
        name : '业绩统计',
        glyphicon : 'glyphicon-home',
        href : 'page.homemanage',
        status : 'home'
      },
      {
        name : '客户管理',
        glyphicon : 'glyphicon-cloud',
        href : 'page.manage',
        status : 'crm',
        showBadge : true
      },
      {
        name : '添加客户',
        glyphicon : 'glyphicon-plus',
        href : 'page.custorm',
        status : 'custorm',
      },
      {
        name : '更多',
        glyphicon : 'glyphicon-option-horizontal',
        status : 'more',
        href : 'page.more'
      }
    ];
    // 如果不是bd或者bdm,没有添加客户的功能
    var bd_arr = [12, 13];
    var role_id = parseInt(daChuLocal.get('role_id'));
    if(role_id != 12){
      tabList.splice(2, 1);
    }
    if(role_id ===null || bd_arr.indexOf(role_id) === -1) {
      //tabList.splice(2, 1);
    }
    $scope.$on('pageChanged',function(event, data) {
      changeTab(data.tabIndex);
    });

    $scope.tabList = tabList;
    $scope.collen = 12/$scope.tabList.length;

    $scope.showType = (function(index) {
      return $scope.tabList[index].status;
    })($state.current.data.tabIndex);
    function changeTab(index) {
      daChuLocal.remove('filter_sift');
      $scope.showType = $scope.tabList[index].status;
    }
  }]);
