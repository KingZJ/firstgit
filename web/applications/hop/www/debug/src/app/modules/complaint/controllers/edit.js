'use strict';

angular
  .module('hop')
  .controller('ComplaintEditCtrl', ['dialog', '$location', '$upload', 'daChuLocal', 'req', '$scope', '$modal', '$window','$cookieStore', '$stateParams', '$state', function(dialog, $location, $upload, daChuLocal, req, $scope, $modal, $window, $cookieStore, $stateParams, $state) {
  $scope.id = $stateParams.id;
  $scope.deal_price = {};
  var getInfo = function() {
    req.getdata('complaint/edit_input', 'POST', function(data){
      if(data.status == 0) {
        $scope.order = data.order;
        $scope.data = data.info;
        $scope.contents = [];
        $scope.imgUploads = data.info.images;
        $scope.ctypeList = data.ctypes;
        $scope.feedbackList = data.feedbacks;
        $scope.statusList = data.statuses;
        $scope.saleList = data.sales;
        $scope.logisticsList = data.logistics;
        $scope.sourceList = data.sources;
        angular.forEach(data.info.contents, function(value, key) {
          angular.forEach(data.order.detail, function(v, k) {
            if(v.id == value.product_id) {
              v.single_price = value.single_price;
              $scope.contents.push({product: v, quantity: value.quantity, sumPrice: value.sum_price, price: value.price});
            }
          });
        });
        angular.forEach($scope.feedbackList, function(value, key) {
          if(value.code == $scope.data.feedback){
            $scope.data.feedback = value;
          }
        });
        angular.forEach($scope.ctypeList, function(value, key) {
          if(value.code == $scope.data.ctype){
            $scope.data.ctype = value;
          }
        });
        angular.forEach($scope.saleList, function(value, key) {
          if(value.id == $scope.data.sale_id){
            $scope.data.sale = value;
          }
        });
        angular.forEach($scope.logisticsList, function(value, key) {
          if(value.id == $scope.data.logistics_id){
            $scope.data.logistics = value;
          }
        });
        angular.forEach($scope.statusList, function(value, key) {
          if(value.code == $scope.data.status){
            $scope.data.status = value;
          }
        });
        angular.forEach($scope.sourceList, function(value, key) {
          if(value.code == $scope.data.source){
            $scope.data.source = value;
          }
        });
      }
    },{id: $scope.id});
  };
  getInfo();

  $scope.back = function() {
    history.go(-1);
  };

  // 增加投诉单内容
  $scope.addContent = function() {
    if($scope.contents.length >= $scope.order.detail.length) {
      alert('投诉单内容产品数量不能超过' + $scope.order.detail.length + '个');
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
      var maxQuantity = parseInt(item.product.quantity * item.product.price / item.product.single_price * 100) / 100;
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
      var maxQuantity = parseInt(item.product.quantity * item.product.price / item.product.single_price);
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
    $scope.data.total_price = parseInt(totalPrice * 100) / 100;
  }

  // 修改投诉单单
  $scope.edit = function(order_number) {
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
        status = $scope.data.status || {code: 0};

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
      id: $scope.data.id,
      orderNumber: $scope.data.order_number,
      source: source.code,
      feedback: feedback.code,
      ctype: ctype.code,
      contents: $scope.contents,
      description: $scope.data.description,
      imgUploads: $scope.imgUploads,
      suggest: $scope.data.suggest,
      progress1: $scope.data.progress1,
      saleId: sale.id,
      progress2: $scope.data.progress2,
      logisticsId: logistics.id,
      progress3: $scope.data.progress3,
      solution : $scope.data.solution,
      status: status.code,
    };

    req.getdata('/complaint/edit', 'POST', function(data) {
      if(data.status == 0) {
        dialog.tips({bodyText:'修改投诉单成功！'});
        req.redirect('/complaint/list');
      } else {
        dialog.tips({bodyText:'修改投诉单失败。'});
      }
    }, postData, true);
  };

  $scope.dialog = dialog.tips;
  // 上传图片
  $scope.$watch('files', function () {
    console.log($scope.imgUploads);
    if($scope.imgUploads && $scope.imgUploads.length >= 5) {
      alert('最多允许上传5张图片！');
      return;
    }
    if($scope.files != undefined) {
      $scope.upload('imgUploads', 'files');
    }
  });
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
        $scope.imgUploads.push(v);
      });
    });
  };
  // 取消上传文件
  $scope.picCancel = function(index) {
    $scope.imgUploads.splice(index, 1);
  }
}]);
