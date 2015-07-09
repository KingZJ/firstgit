'use strict'
angular.module('hop').controller('LineListCtrl',['$location', 'dialog', 'req', '$scope', function($location, dialog, req, $scope){
  var init = function() {
    req.getdata('line/list_options', 'POST', function(data) {
      if(data.status == 0) {
        $scope.cityList = data.cities;
        $scope.siteList = data.sites;
      }
    });
  };

  // 初始化数据
  init();
  // 重新获取分页数据
  var getList = function() {
    var site      = $scope.site || {id: 0},
        city      = $scope.city || {id: 0};

    var postData = {
      cityId: city.id,
      siteId: site.id,
      searchValue: $scope.searchValue,
      startTime: Date.parse($scope.startTime),
      endTime: Date.parse($scope.endTime),
      currentPage: $scope.paginationConf.currentPage,
      itemsPerPage: $scope.paginationConf.itemsPerPage,
    };
    req.getdata('line/lists', 'POST', function(data) {
      if(data.status == 0) {
        // 变更分页的总数
        $scope.paginationConf.totalItems = data.total;
        // 变更数据条目
        $scope.list = data.list;
      }
    }, postData);
  };
  // 分页参数初始化
  $scope.paginationConf = {
    currentPage: 1,
    itemsPerPage: 15
  };
  // 通过$watch currentPage和itemperPage 当他们一变化的时候，重新获取数据条目
  $scope.$watch('paginationConf.currentPage + paginationConf.itemsPerPage', getList);
  // 判断按钮是否显示
  /*$scope.auth = {
    create: HopAuth.check_auth('line', 'create'),
    edit: HopAuth.check_auth('line', 'edit'),
    delete: HopAuth.check_auth('line', 'delete'),
  };*/

  // 按照条件筛选
  $scope.search = function(){
    getList();
  };
  // 重置搜索条件
  $scope.reset = function() {
    $scope.city = '';
    $scope.site = '';
    $scope.searchValue = '';
    getList();
  };
  // 删除数据
  $scope.delete = function($index) {
    dialog.tips({
      actionText: '确定' ,
      bodyText: '确定删除线路[' + $scope.list[$index].name + ']吗?',
      ok: function() {
        req.getdata('line/delete', 'POST', function(data) {
          if(data.status == 0) {
            dialog.tips({bodyText:'删除成功！'});
            getList();
          }else{
            dialog.tips({bodyText:'删除失败！' + data.msg});
          }
        }, {id:$scope.list[$index].id});
      }
    });
  };
}]);
