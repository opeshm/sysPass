<?php

/** @var $icons \Theme\Icons */
/** @var $customer \SP\DataModel\ClientData */

?>

<div id="box-popup">
    <h2 class="center"><?php echo $header; ?><i class="btn-popup-close material-icons">close</i></h2>

    <form method="post" name="frmCustomers" id="frmCustomers" class="form-action"
          data-onsubmit="appMgmt/save"
          data-route="<?php echo isset($route) ? $route : ''; ?>"
          data-activetab="<?php echo isset($activeTab) ? $activeTab : ''; ?>"
          data-nextaction="<?php echo $nextAction; ?>"
          data-hash="">
        <table class="popup-data">
            <tbody>
            <tr>
                <td class="descField"><?php echo __('Nombre'); ?></td>
                <td class="valField">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input id="name" name="name" type="text" required
                               class="mdl-textfield__input mdl-color-text--indigo-400"
                               maxlength="50" value="<?php echo $client->getCustomerName(); ?>">
                        <label class="mdl-textfield__label"
                               for="name"><?php echo __('Nombre del cliente'); ?></label>
                    </div>
                </td>
            </tr>

            <tr>
                <td class="descField"><?php echo __('Descripción'); ?></td>
                <td class="valField">
                    <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
                        <input id="description" name="description" type="text"
                               class="mdl-textfield__input mdl-color-text--indigo-400"
                               maxlength="50" value="<?php echo $client->getCustomerDescription(); ?>">
                        <label class="mdl-textfield__label"
                               for="description"><?php echo __('Descripción del cliente'); ?></label>
                    </div>
                </td>
            </tr>

            <tr>
                <td class="descField">
                    <div id="help-isglobal"
                         class="icon material-icons <?php echo $icons->getIconHelp()->getClass(); ?>"><?php echo $icons->getIconHelp()->getIcon(); ?></div>
                    <div class="mdl-tooltip mdl-tooltip--large" for="help-isglobal">
                        <p>
                            <?php echo __('Indica si el cliente es visible para todos los usuarios.'); ?>
                        </p>
                        <p>
                            <?php echo __('Por defecto los clientes asignados a cuentas sólo son visibles para los usuarios con acceso a las cuentas.'); ?>
                        </p>
                    </div>
                </td>
                <td class="valField">
                    <label class="mdl-switch mdl-js-switch mdl-js-ripple-effect" for="isglobal">
                        <input type="checkbox" id="isglobal"
                               class="mdl-switch__input mdl-color-text--indigo-400"
                               name="isglobal" <?php echo $client->getCustomerIsGlobal() ? 'checked' : ' '; ?>/>
                        <span class="mdl-switch__label"><?php echo __('Global'); ?></span>
                    </label>
                </td>
            </tr>

            <?php if ($customFields): ?>
                <?php include $this->includeTemplate('aux-customfields', 'common'); ?>
            <?php endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="sk" value="">
        <input type="hidden" name="isAjax" value="1">
    </form>
    <div class="action-in-box">
        <button
                class="mdl-button mdl-js-button mdl-button--fab mdl-button--mini-fab mdl-button--colored <?php echo $icons->getIconSave()->getClassButton(); ?>"
                form="frmCustomers" title="<?php echo $icons->getIconSave()->getTitle(); ?>">
            <i class="material-icons"><?php echo $icons->getIconSave()->getIcon(); ?></i>
        </button>
    </div>
</div>
<script>
    sysPassApp.sk.set("<?php echo $sk; ?>");
</script>