'use strict';

angular
  .module('hop')
  .controller('CateMapCtrl', ['$scope', 'req', 'dialog', 'daChuLocal' , function($scope, req, dialog , daChuLocal) {
  $scope.title = '映射列表';
  $scope.key = '', $scope.localId = '';
  // 获取分类列表
  var  cateList = function() {
    $scope.list = [], $scope.suggest = [];
    req.getdata('catemap/lists', 'POST', function(data) {
      $scope.list = data.list;
      $scope.paginationConf.totalItems = data.total;
      $scope.locations = data.location;
      $scope.customer_type_options = data.customer_type_options;
      // 取缓存客户类型
      if(daChuLocal.get('default_type') ){
        var current_type = daChuLocal.get('default_type');
        angular.forEach(data.customer_type_options , function(v){
          if(v.value == current_type.value){
            $scope.defulat_type = v;
          }
        })
      } else {
        $scope.defulat_type = data.customer_type_options && data.customer_type_options.length != 0  ?  data.customer_type_options[0] : false;
      }

      // 取缓存城市
      if(daChuLocal.get('locate')){
        var current_loca = daChuLocal.get('locate');
        $scope.localId = current_loca.id;
        angular.forEach(data.location, function(v){
          if(v.id == current_loca.id){
            $scope.locate = v;
          }
        })
      } else {
        $scope.locate = data.location[0];
      }
      $scope.suggest = data.list;
      $scope.reBackbtn = false;
    },
    {
      currentPage: $scope.paginationConf.currentPage,
      itemsPerPage: $scope.paginationConf.itemsPerPage,
      // 取缓存城市 
      locationId : $scope.localId,
      searchVal : $scope.key ,
      status : $scope.status ,
      // 如果选择了用户类型 ，就传过去， 默认为1 ＝普通用户
      customerType : daChuLocal.get('default_type') ?  daChuLocal.get('default_type').value : 1
    });
  }
  // 根据条件来筛选
  $scope.filterByStatus = function(status) {
    $scope.status = status;
    cateList();
  }
  // 查询
  $scope.search = function(state) {
    if(parseInt(state) === 1) {
      $scope.key = '';
    }

    daChuLocal.set('locate' , $scope.locate );
    daChuLocal.set('default_type' , $scope.defulat_type );
    $scope.localId = $scope.locate.id;
    cateList();
  }
  // 分页参数初始化
  $scope.paginationConf = {
    currentPage: 1,
    itemsPerPage: 15
  };
  // 通过$watch currentPage和itemperPage 当他们一变化的时候，重新获取数据条目
  $scope.$watch(
    'paginationConf.currentPage + paginationConf.itemsPerPage',
    cateList
  );

  // 设置状态
  $scope.setStatus = function($index, status) {
    $scope.list[$index].status = status;
    var cateId = $scope.list[$index].id;
    req.getdata('catemap/set_status', 'POST', function(data) {
      dialog.tips({bodyText:data.msg});
    }, {id:cateId, status: status});
  }
  }]);

