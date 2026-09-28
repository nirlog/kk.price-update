<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

global $APPLICATION, $USER;

if (!$USER->CanDoOperation('edit_php')) {
    $APPLICATION->AuthForm('Доступ запрещен');
    die();
}

if (!Loader::includeModule('catalog') || !Loader::includeModule('iblock')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die('Необходимы модули catalog и iblock');
}

$moduleId = 'kk.price.update';
$settings = json_decode((string)Option::get($moduleId, 'default_price_settings', '{}'), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $iblockId = (int)($_POST['IBLOCK_ID'] ?? 0);
    $priceTypes = json_decode((string)($_POST['PRICE_TYPES'] ?? '[]'), true) ?: [];
    $adjustments = json_decode((string)($_POST['PRICE_ADJUSTMENTS'] ?? '{}'), true) ?: [];

    if ($iblockId > 0) {
        $normalized = [];
        foreach ($priceTypes as $typeId) {
            $typeId = (int)$typeId;
            if ($typeId > 0) {
                $normalized[$typeId] = trim((string)($adjustments[$typeId] ?? ''));
            }
        }
        $settings[$iblockId] = $normalized;
        Option::set($moduleId, 'default_price_settings', json_encode($settings, JSON_UNESCAPED_UNICODE));
        LocalRedirect($APPLICATION->GetCurPageParam('saved=Y', ['saved']));
    }
}

$catalogIblocks = [];
$res = CCatalog::GetList([], ['PRODUCT_IBLOCK_ID' => 0]);
while ($catalog = $res->Fetch()) {
    $iblock = CIBlock::GetByID($catalog['IBLOCK_ID'])->Fetch();
    if ($iblock) {
        $catalogIblocks[] = $iblock;
    }
}

$priceTypes = [];
$priceTypeRes = CCatalogGroup::GetList(['SORT' => 'ASC', 'NAME' => 'ASC']);
while ($priceType = $priceTypeRes->Fetch()) {
    $priceTypes[] = $priceType;
}

$APPLICATION->SetTitle('Настройки по умолчанию: типы цен и корректировки');
?>
<?php if (isset($_GET['saved']) && $_GET['saved'] === 'Y'): ?>
    <div class="adm-info-message" style="margin: 12px 0;">Настройки сохранены.</div>
<?php endif; ?>

<form method="post" id="defaults-form">
    <?= bitrix_sessid_post() ?>
    <div style="margin-bottom: 12px;">
        <label><b>Каталог:</b></label><br>
        <select id="iblock-select" name="IBLOCK_ID" required style="min-width: 340px; padding: 5px;">
            <option value="">- Выберите каталог -</option>
            <?php foreach ($catalogIblocks as $iblock): ?>
                <option value="<?= (int)$iblock['ID'] ?>">[<?= (int)$iblock['ID'] ?>] <?= htmlspecialcharsbx($iblock['NAME']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-bottom: 12px;">
        <label><b>Типы цен:</b></label><br>
        <select id="price-types-select" multiple style="min-width: 340px; min-height: 120px; padding: 5px;">
            <?php foreach ($priceTypes as $priceType): ?>
                <option value="<?= (int)$priceType['ID'] ?>">[<?= (int)$priceType['ID'] ?>] <?= htmlspecialcharsbx($priceType['NAME']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="adjustments-container" style="margin-bottom: 16px;"></div>

    <input type="hidden" name="PRICE_TYPES" id="price-types-hidden" value="[]">
    <input type="hidden" name="PRICE_ADJUSTMENTS" id="price-adjustments-hidden" value="{}">

    <button type="submit" class="adm-btn-save">Сохранить настройки</button>
</form>

<script>
const defaultsByIblock = <?= CUtil::PhpToJSObject($settings) ?>;
const iblockSelect = document.getElementById('iblock-select');
const priceTypesSelect = document.getElementById('price-types-select');
const adjustmentsContainer = document.getElementById('adjustments-container');
const typesHidden = document.getElementById('price-types-hidden');
const adjHidden = document.getElementById('price-adjustments-hidden');

function renderAdjustments(prefill = {}) {
    const selected = Array.from(priceTypesSelect.selectedOptions);
    adjustmentsContainer.innerHTML = '';
    selected.forEach(option => {
        const typeId = option.value;
        const row = document.createElement('div');
        row.style.marginBottom = '8px';
        row.innerHTML = '<label style="display:inline-block; min-width:250px;">' + option.textContent + ':</label>' +
            '<input type="text" data-adjust="' + typeId + '" value="' + (prefill[typeId] || '') + '" placeholder="+1000, -2500, +20%, -10%" style="min-width:280px; padding:4px;">';
        adjustmentsContainer.appendChild(row);
    });
}

iblockSelect.addEventListener('change', () => {
    const iblockId = iblockSelect.value;
    const defaults = defaultsByIblock[iblockId] || {};
    Array.from(priceTypesSelect.options).forEach(opt => {
        opt.selected = Object.prototype.hasOwnProperty.call(defaults, opt.value);
    });
    renderAdjustments(defaults);
});

priceTypesSelect.addEventListener('change', () => renderAdjustments(defaultsByIblock[iblockSelect.value] || {}));

document.getElementById('defaults-form').addEventListener('submit', () => {
    const selectedIds = Array.from(priceTypesSelect.selectedOptions).map(o => o.value);
    const adjustments = {};
    selectedIds.forEach(typeId => {
        const input = document.querySelector('[data-adjust="' + typeId + '"]');
        adjustments[typeId] = input ? input.value.trim() : '';
    });
    typesHidden.value = JSON.stringify(selectedIds);
    adjHidden.value = JSON.stringify(adjustments);
});
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
