'use strict';

angular
  .module('dachuwang')
  .config(function ($stateProvider, $urlRouterProvider, $locationProvider) {
    var tempDir = 'app/modules/',
    componentDir = 'components/',
    sysTitle = '大厨CRM',
    bd_and_am = [12,14];
    var tabMap = {
      home : 0,
      crm : 1,
      custorm: 2,
      more : 3
    };
    function getLocalStorage(name) {
      var temp = localStorage.getItem(name);
      if(temp) {
        return parseInt(JSON.parse(temp));
      }
      return null;
    }
    function isNotManage() {
      var role_id = getLocalStorage('role_id');
      if(role_id ===null || bd_and_am.indexOf(role_id)!==-1) {
        return true;
      } else {
        return false;
      }
    }
    $stateProvider
      // 通用模板
      .state('page', {
        url : '/',
        data : {
          pageTitle : sysTitle,
          tabIndex : tabMap.home
        },
        views: {
          '' : {
            templateUrl : componentDir+'page/page.html',
            controller: 'pageCtrl'
          },
          '@page' : {
            templateUrl : function() {
              if(isNotManage()) {
                return tempDir+'home/home.html';
              }
              return tempDir+'home/dblist.html';
            },
            controllerProvider : function() {
              if(isNotManage()) {
                return 'homeCtrl';
              }
              return 'DblistController';
            }
          }
        }
      })
      .state('page.home', {
        url : 'home/{bd_id:.*}',
        templateUrl : tempDir+'home/home.html',
        controller : 'homeCtrl',
        data : {
          pageTitle : sysTitle,
          tabIndex : tabMap.home,
          showBack : (isNotManage()) ? false:true
        }
      })
      .state('page.homemanage', {
        url : 'statistic',
        templateUrl : function() {
          if(isNotManage()) {
            return tempDir+'home/home.html';
          }
          return tempDir+'home/dblist.html';
        },
        controllerProvider : function() {
          if(isNotManage()) {
            return 'homeCtrl';
          }
          return 'DblistController';
        },
        data : {
          pageTitle : sysTitle,
          tabIndex : tabMap.home,
        }
      })
      // 登录页
      .state('loginpage', {
        url : '/user/login',
        data : {
          pageTitle : '登录'
        },
        templateUrl : tempDir+'user/login.html',
        controller : 'loginCtrl'
      })
      // 修改密码
      .state('page.password', {
        url : 'user/password',
        data : {
          pageTitle : '修改密码',
          showBack : true
        },
        templateUrl : tempDir+'user/password.html',
        controller : 'userPassCtrl'
      })
      .state('page.editcustomer', {
        url: 'edit/:customer_id',
        data : {
          pageTitle : '编辑客户',
          showBack : true
        },
        templateUrl : tempDir+'custorm/editcustomer.html',
        controller : 'EditCustomerController'
      })
      // 客户列表相关
      .state('page.crm', {
        url : 'crm/:invite_id',
        data : {
          pageTitle: '客户管理',
          tabIndex : tabMap.crm,
          showBack : true,
          showMap  : true
        },
        templateUrl : function() {
          return tempDir+'custorm/list.html';
        },
        controller : 'crmCtrl'
      })
      //潜在客户列表
      .state('page.potlist', {
        url : 'potlist/:invite_id',
        data : {
          pageTitle : '潜在客户',
          tabIndex : tabMap.crm,
          showBack : true
        },
        templateUrl : tempDir+'custorm/potlist.html',
        controller : 'PotlistController'
      })
      .state('page.openpot', {
        url : 'openpot/:potid',
        data : {
          pageTitle : '添加客户',
          tabIndex : tabMap.custorm,
          showBack : true
        },
        templateUrl : tempDir+'custorm/openpot.html',
        controller : 'OpenpotController'
      })
      .state('page.editpot', {
        url : 'editpot/:potid',
        data : {
          pageTitle : '编辑客户',
          tabIndex : tabMap.custorm,
          showBack : true
        },
        templateUrl : tempDir+'custorm/editpot.html',
        controller : 'EditpotController'
      })
      //管理
      .state('page.manage', {
        url : 'manage',
        data : {
          pageTitle: '客户管理',
          tabIndex : tabMap.crm,
          showMap  : true
        },
        templateUrl : function() {
          if(isNotManage()) {
            return tempDir+'custorm/list.html';
          } else {
            return tempDir+'custorm/manage.html';
          }
        },
        controllerProvider : function() {
          if(isNotManage()) {
            return 'crmCtrl';
          } else {
            return 'manageController';
          }
        }
      })
      // 商品列表相关
      .state('page.custorm', {
        url : 'custorm',
        data : {
          pageTitle : '添加客户',
          tabIndex : tabMap.custorm
        },
        templateUrl : function() {
          return tempDir+'custorm/create.html';
        },
        controller : 'custormCtrl'
      })
      //添加潜在客户
      .state('page.potential', {
        url : 'potential',
        data : {
          pageTitle : '潜在客户',
          tabIndex : tabMap.custorm,
          showBack : true
        },
        templateUrl : tempDir+'custorm/potential.html',
        controller : 'PotentialController'
      })
      // 个人中心相关
      .state('page.userCenter', {
        url : 'user/center',
        templateUrl : tempDir+'user/center.html',
        controller : 'UserCenterCtrl',
        data : {
          pageTitle : '个人中心',
          tabIndex : tabMap.more,
          showBack : true
        },
        /*resolve : {
          userService : 'userService',
          userInfo : function(userService) {
            return userService.baseInfo();
          }
        }*/
      })
      .state('page.seainfodetail', {
        url : 'seainfo/{uid:int}',
        templateUrl : tempDir+'cdetail/seainfo.html',
        controller : 'SeainfoController',
        data : {
          pageTitle : '客户详情',
          tabIndex : tabMap.more,
          showBack : true
        }
      })
      .state('page.more', {
        url : 'more',
        templateUrl : tempDir+'more/more.html',
        controller : 'MoreController',
        data : {
          pageTitle : '更多',
          tabIndex : tabMap.more
        }
      })
      //客户详情,默认展示客户信息
      .state('page.customerdetail', {
        url : 'cdetail/{uid:int}',
        data : {
          pageTitle : '客户详情',
          tabIndex : tabMap.crm,
          showBack : true
        },
        views : {
          '' : {
            templateUrl : tempDir+'cdetail/cdetailnav.html',
            controller : 'CdetailNavController'
          },
          '@page.customerdetail' : {
            templateUrl : tempDir+'cdetail/cdetailinfo.html',
            controller : 'CdetailInfoController'
          }
        }
      })
      //客户历史归属
      .state('page.customerdetail.cdetailhistory', {
        url : '/his',
        data : {
          pageTitle : '历史归属',
          tabIndex : tabMap.crm,
          showBack : true
        },
        templateUrl : tempDir+'cdetail/cdetailhis.html',
        controller : 'CdetailHisController'
      })
      //单独的页面不带头部
      .state('page.cdetailhistory', {
        url : '{uid:int}/history',
        data : {
          pageTitle : '历史归属',
          tabIndex : tabMap.crm,
          showBack : true
        },
        templateUrl : tempDir+'cdetail/history.html',
        controller : 'HisController'
      })
      //客户动态列表
      .state('page.customerdetail.trends', {
        url : '/trends',
        data : {
          pageTitle : '客户动态',
          tabIndex : tabMap.crm,
          showBack : true
        },
        templateUrl : tempDir+'cdetail/trends.html',
        controller : 'CtrendsController'
      })
      //客户动态详情
      .state('page.customerdetail.orderdetail', {
        url : '/order/{order_number}',
        data : {
          pageTitle : '订单详情',
          tabIndex : tabMap.crm,
          showBack : true
        },
        templateUrl : tempDir+'cdetail/orderdetail.html',
        controller : 'CtrendsOrderController'
      });
    $urlRouterProvider.otherwise('/home');
    $locationProvider.html5Mode(true);
  });
