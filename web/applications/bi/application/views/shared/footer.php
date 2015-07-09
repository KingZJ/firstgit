	
        </div><!-- div rows -->
    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="http://cdn.bootcss.com/jquery/1.11.2/jquery.min.js"></script>
    <script src="http://cdn.bootcss.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <script src="http://cdn.bootcss.com/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js" charset="UTF-8"></script>
    <script src="http://cdn.bootcss.com/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.zh-CN.min.js" charset="UTF-8"></script>
    <script type="text/javascript" src="<?php echo $base_url ?>/resource/js/jquery.cookie.js"></script>
    <script type="text/javascript" src="<?php echo $base_url ?>/resource/js/bi.js?v=<?php echo $js_version;?>"></script>
    <!--echart图标加载 -->
    <script src="http://echarts.baidu.com/build/dist/echarts.js"></script>
    <?php if (isset($current_url) && substr($current_url, -8) === 'order_td'){?>
    <script type="text/javascript" src="<?php echo $base_url ?>/resource/js/bi_order_td.js?v=<?php echo $js_version;?>"></script>
    <?php }?>

    <!--customer_detail图表-->
    <?php if (isset($current_url) && substr($current_url, -15) === 'show_cus_detail'){?>
    <script type="text/javascript" src="<?php echo $base_url ?>/resource/js/bi_customer_detail.js?v=<?php echo $js_version;?>"></script>
    <?php }?>
    </body>
</html>
