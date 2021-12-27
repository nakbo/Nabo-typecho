<?php
include 'header.php';
include 'menu.php';
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-3">
                <p><a href="http://gravatar.com/emails/" title="<?php _e('在 Gravatar 上修改头像'); ?>">
                        <?php echo '<img class="profile-avatar" style="border-radius: 20px;" src="' . Typecho_Common::gravatarUrl($user->mail, 160, 'X', 'mm', $request->isSecure()) . '" alt="' . $user->screenName . '" />'; ?>
                    </a>
                </p>
                <br>
                <div class="typecho-table-wrap" style="padding: 15px 15px 8px;border-radius: 10px">
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="50%">
                            <col width="50%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>名称</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $manifest = Nabo_Plugin::MANIFEST; ?>
                        <tr>
                            <td>引擎</td>
                            <td><?= $manifest['engine']; ?></td>
                        </tr>
                        <tr>
                            <td>插件版本</td>
                            <td><?= $manifest['versionName']; ?></td>
                        </tr>
                        <tr>
                            <td>适用南博</td>
                            <td><?= "{$manifest['versionName']}.x"; ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-mb-12 col-tb-6 col-tb-offset-1 typecho-content-panel" role="form">
                <section id="change-password">
                    <h3><?php _e("个人配置"); ?></h3>
                    <?php Nabo_Plugin::profile()->render(); ?>
                </section>

                <br>

                <section id="change-password">
                    <h3><?php _e("相关链接"); ?></h3>
                    <button onclick="window.open('https://nabo.krait.cn/')" class="btn btn-nabo">南博官网</button>
                    <button onclick="window.open('https://nabo.krait.cn/docs/#/start')" class="btn btn-nabo">使用文档
                    </button>
                    <style>
                        .btn-nabo {
                            background-color: white;
                            border-radius: 3px;
                            margin-right: 1em
                        }
                    </style>
                </section>

                <?php if ($user->pass('administrator', true)): ?>
                    <br>
                    <section id="change-password">
                        <h3><?php _e("插件配置"); ?></h3>
                        <?php Typecho_Widget::widget('Widget_Plugins_Config', null, ['config' => 'Nabo'])->config()->render(); ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
include 'footer.php';
?>
