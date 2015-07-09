'use strict';

angular
  .module('hop')
  .controller('ComplaintCreateCtrl', ['dialog', '$location', '$upload', 'daChuLocal', 'req', '$scope', '$modal', '$window','$cookieStore', '$stateParams', '$state', function(dialog, $location, $upload, daChuLocal, req, $scope, $modal, $window, $cookieStore, $stateParams, $state) {
  $scope.data = '';
  $scope.deal_price = {};
  $scope.order_number = $stateParams.order_number;
  var getInfo = function() {
    req.getdata('complaint/create_input', 'POST', function(data){
      if(data.status == 0) {
        $scope.data = data.info;
        $scope.ctypeList = data.ctypes;
        $scope.feedbackList = data.feedbacks;
        $scope.statusList = data.statuses;
        $scope.saleList = data.sales;
        $scope.logisticsList = data.logistics;
        $scope.sourceList = data.sources;
        $scope.data.status = $scope.statusList[0];
      }
    },{order_number: $scope.order_number});

    $scope.contents = [{product:{}, quantity:1, sumPrice:0}];
  };
  getInfo();

  $scope.back = function() {
    history.go(-1);
  };

  // 增加投诉单内容
  $scope.addContent = function() {
    if($scope.contents.length >= $scope.data.detail.length) {
      alert('投诉单内容产品数量不能超过' + $scope.data.detail.length + '个');
      return;
    }
    $scope.contents.push({product:{}, quantity:1, sumPrice:0});
  }
  // 删除投诉单内容
  $scope.removeContent = function(index) {
    $scope.contents.splice(index, 1);
    $scope.calcProduct();
  }
  $scope.changeProduct = function(index) {
    var item = $scope.contents[index];
    var selProduct = item.product || {id:0};
    // 判断用户输入是否超出订单产品数量
    if(item.product){
      var maxQuantity = parseInt(item.product.quantity * item.product.price / item.product.single_price);
      if(item.quantity > maxQuantity){
        item.quantity = maxQuantity;
      }
    }
    // 判断是否重复
    angular.forEach($scope.contents, function(v, k) {
      if(k != index && v.product.id == selProduct.id) {
        alert('投诉单内容中不能选择重复的产品！请重新选择');
        $scope.contents[index] = {product:{}, quantity:1, sumPrice:0};
        return;
      }
    });

    $scope.calcProduct();
  }

  $scope.backUpNum = {};
  $scope.clearNum = function(item) {
    var product = item.product || {id:0};
    $scope.backUpNum[product.id] = item.quantity;
    item.quantity = "";
    $scope.calcProduct();
  }


  $scope.setNum = function(item, force) {
    var product = item.product || {id:0};
    if(product.id == 0) {
      item.quantity = 1;
      return;
    }
    // 判断用户输入是否超出订单产品数量
    var maxQuantity = parseInt(item.product.quantity * item.product.price / item.product.single_price * 100) / 100;
    if(item.quantity > maxQuantity){
      item.quantity = maxQuantity;
    }
    force = force ? force : false;
    if(force && item.quantity === "" && $scope.backUpNum[product.id]) {
      item.quantity = $scope.backUpNum[product.id];
      $scope.backUpNum[product.id] = "";
    }
    if(item.quantity != null && item.quantity <= 0) {
      item.quantity = 0;
    } else if(item.quantity != null || force) {
      if(item.quantity <= 0) {
        $scope.remove(item);
      } else if(!/^\d+\.?\d*$/.test(item.quantity)){
        item.quantity = 1;
      }
    }

    $scope.calcProduct();
  }

  // 重新计算投诉单产品价格
  $scope.calcProduct = function(item) {
    if(item) {
      var maxQuantity = parseInt(item.product.quantity * item.product.price / item.product.single_price * 100) / 100;
      if(item.quantity > maxQuantity){
        item.quantity = maxQuantity;
      }
    }
    var totalPrice = 0;
    angular.forEach($scope.contents, function(v) {
      if(v.product && v.product.id){
        v.sumPrice = parseInt(v.product.single_price * v.quantity * 100) / 100;
        totalPrice += v.sumPrice;
      }
    });
    $scope.totalPrice = parseInt(totalPrice * 100) / 100;
  }

  // 创建投诉单
  $scope.create = function() {
    $scope.show_error = true;
    $scope.basic_form.$setDirty();
    if($scope.basic_form.$invalid) {
      return;
    }

    var feedback = $scope.data.feedback || {code:0},
        source = $scope.data.source || {code:0},
        ctype = $scope.data.ctype || {code:0},
        sale = $scope.data.sale || {id: 0},
        logistics = $scope.data.logistics || {id: 0},
        status = $scope.data.status || {code: 0},
        imgUploads = $scope.imgUploads || [];

    var error = false;
    // 判断是否选择投诉单内容
    angular.forEach($scope.contents, function(v, k) {
      if(!v || !v.product || !v.product.id) {
        error = true;
        return;
      }
    });

    if(error) {
      alert('请选择投诉单内容！');
      return;
    }

    var postData = {
      orderNumber: $scope.data.order_number,
      source: source.code,
      feedback: feedback.code,
      ctype: ctype.code,
      contents: $scope.contents,
      totalPrice: $scope.totalPrice,
      description: $scope.data.description,
      imgUploads: imgUploads,
      suggest: $scope.data.suggest,
      progress1: $scope.data.progress1,
      saleId: sale.id,
      progress2: $scope.data.progress2,
      logisticsId: logistics.id,
      progress3: $scope.data.progress3,
      solution : $scope.data.solution,
      status: status.code,
    };

    req.getdata('/complaint/create', 'POST', function(data) {
      if(data.status == 0) {
        dialog.tips({bodyText:'添加投诉单成功！'});
        req.redirect('/complaint/list');
      } else {
        dialog.tips({bodyText:'添加投诉单失败。'});
      }
    }, postData, true);

  };
  $scope.dialog = dialog.tips;
  // 上传图片
  $scope.$watch('files', function () {
    if($scope.imgUploads && $scope.imgUploads.length >= 5) {
      alert('最多允许上传5张图片！');
      return;
    }
    if($scope.files != undefined) {
      $scope.upload('imgUploads', 'files');
    }
  });
  var imgUpload = [];
  // 上传文件
  $scope.upload = function (key, name) {
    angular.forEach($scope[name], function(v) {
      $upload.upload({
        url: 'http://img.dachuwang.com/upload?bucket=misc',
        file: v,
        fileFormDataName: 'files[]'
      }).progress(function (evt) {
        var progressPercentage = parseInt(100.0 * evt.loaded / evt.total);
        v['progressPercentage'] = progressPercentage;
      }).success(function (data, status, headers, config) {
        // 成功后预览
        v['dataUrl'] = data['files'][0]['url'];
        v['size'] += 'bytes';
        imgUpload.push(v);
        $scope[key] = imgUpload;
        daChuLocal.set(key, $scope[key]);
      });
    });
  };
  // 取消上传文件
  $scope.picCancel = function(index) {
    $scope.imgUploads.splice(index, 1);
  }
}]);
