ALTER TABLE t_suborder add column customer_type tinyint(1) not null default 1 comment '1 普通用户 2 KA用户';
