<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

global $APPLICATION, $USER;

// Проверка прав доступа
if (!$USER->CanDoOperation('edit_php')) {
    $APPLICATION->AuthForm("Доступ запрещен");
    die();
}

$APPLICATION->SetTitle("Обновление цен компьютеров");

// Проверяем установлены ли необходимые модули
if (!Loader::includeModule('catalog') || !Loader::includeModule('iblock')) {
    echo "Для работы модуля необходимы модули catalog и iblock";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

// Простая проверка работы
echo "<h1>Модуль обновления цен компьютеров</h1>";
echo "<p>Модуль загружается...</p>";

// Получаем список каталогов
$catalogIblocks = [];
$res = CCatalog::GetList([], ['PRODUCT_IBLOCK_ID' => 0]);
while ($catalog = $res->Fetch()) {
    $iblock = CIBlock::GetByID($catalog['IBLOCK_ID'])->Fetch();
    if ($iblock) {
        $catalogIblocks[] = [
            "ID" => $iblock["ID"],
            "NAME" => $iblock["NAME"],
            "CODE" => $iblock["CODE"],
            "LID" => $iblock["LID"]
        ];
    }
}

echo "<p>Найдено каталогов: " . count($catalogIblocks) . "</p>";
?>

<div id="kk-price-update-app">
    <form id="kk-price-form" method="POST">
        <?= bitrix_sessid_post() ?>
        
        <div style="margin-bottom: 20px;">
            <label for="iblock-select" style="display: block; margin-bottom: 5px; font-weight: bold;">
                Выберите каталог:
            </label>
            <select id="iblock-select" name="IBLOCK_ID" style="min-width: 300px; padding: 5px;">
                <option value="">- Выберите каталог -</option>
                <?php foreach ($catalogIblocks as $iblock): ?>
                    <option value="<?= $iblock['ID'] ?>">[<?= $iblock['ID'] ?>] <?= htmlspecialcharsbx($iblock['NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="properties-container" style="display: none; margin-bottom: 20px;">
            <!-- Сюда будут загружаться свойства через AJAX -->
        </div>

        <div id="values-container" style="display: none; margin-bottom: 20px;">
            <!-- Сюда будут загружаться значения свойств -->
        </div>

        <div id="price-types-container" style="display: none; margin-bottom: 20px;">
            <label for="price-types-select" style="display: block; margin-bottom: 5px; font-weight: bold;">
                Типы цен для обновления:
            </label>
            <select id="price-types-select" multiple style="min-width: 300px; min-height: 120px; padding: 5px;">
                <?php
                $priceTypeRes = CCatalogGroup::GetList(['SORT' => 'ASC', 'NAME' => 'ASC']);
                while ($priceType = $priceTypeRes->Fetch()):
                ?>
                    <option value="<?= (int)$priceType['ID'] ?>">[<?= (int)$priceType['ID'] ?>] <?= htmlspecialcharsbx($priceType['NAME']) ?></option>
                <?php endwhile; ?>
            </select>
            <p style="margin: 6px 0 0; color: #666;">Можно выбрать несколько типов цен.</p>
            <div id="korsac-pricing-note" style="display:none; margin-top:10px; padding:10px; background:#eef8ff; border-left:3px solid #3bc8f5;">
                Для KORSAC цена рассчитывается по Pricing Policy модуля kk.korsac.<br>
                Ручная корректировка цены в этом режиме не применяется.
            </div>
            <div id="price-adjustments" style="margin-top: 10px;"></div>
        </div>

        <button type="button" id="start-update-btn" style="display: none; padding: 10px 20px; background: #3bc8f5; color: white; border: none; border-radius: 3px; cursor: pointer;">
            Обновить цены
        </button>
    </form>

    <div id="console-log" style="display: none; margin-top: 30px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 3px; max-height: 400px; overflow-y: auto;">
        <h3>Лог выполнения:</h3>
        <div id="log-content"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iblockSelect = document.getElementById('iblock-select');
        const propertiesContainer = document.getElementById('properties-container');
        const valuesContainer = document.getElementById('values-container');
        const priceTypesContainer = document.getElementById('price-types-container');
        const priceTypesSelect = document.getElementById('price-types-select');
        const priceAdjustments = document.getElementById('price-adjustments');
        const korsacPricingNote = document.getElementById('korsac-pricing-note');
        const startUpdateBtn = document.getElementById('start-update-btn');
        const consoleLog = document.getElementById('console-log');
        const logContent = document.getElementById('log-content');

        // Получаем sessid из PHP
        const bitrixSessid = '<?= bitrix_sessid() ?>';
        
        let currentIblockId = null;
        let currentPropertyId = null;
        let currentHlBlockId = null;
        let currentPropertyMode = null;
        
        // Переменные для отслеживания состояния процесса
        let updateInProgress = false;
        let currentProcessState = {
            currentValueIndex: 0,
            processedElements: 0,
            totalSuccess: 0,
            totalErrors: 0,
            totalSkippedNoPriceUpdate: 0,
            totalOffersSuccess: 0,
            totalOffersProcessed: 0,
            errorFile: null,
            totalValues: 0
        };

        iblockSelect.addEventListener('change', function() {
            currentIblockId = this.value;
            
            if (!currentIblockId) {
                resetForm();
                return;
            }

            showLoading(propertiesContainer, 'Загрузка свойств...');
            propertiesContainer.style.display = 'block';
            valuesContainer.style.display = 'none';
            startUpdateBtn.style.display = 'none';
            priceTypesContainer.style.display = 'none';
            priceAdjustments.innerHTML = '';

            loadPriceDefaults(currentIblockId);

            const formData = new FormData();
            formData.append('IBLOCK_ID', currentIblockId);
            formData.append('sessid', bitrixSessid);

            fetch('/bitrix/admin/get_properties.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                propertiesContainer.innerHTML = html;
                const propertySelect = document.getElementById('property-select');
                if (propertySelect) {
                    propertySelect.addEventListener('change', handlePropertyChange);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                propertiesContainer.innerHTML = '<p style="color: red;">Ошибка загрузки свойств</p>';
            });
        });

        function handlePropertyChange(e) {
            currentPropertyId = e.target.value;
            const selectedOption = e.target.options[e.target.selectedIndex];
            currentHlBlockId = selectedOption.getAttribute('data-hl-block');
            currentPropertyMode = selectedOption.getAttribute('data-mode');
            updateAdjustmentMode();
            
            if (!currentPropertyId || !currentHlBlockId) {
                valuesContainer.style.display = 'none';
                startUpdateBtn.style.display = 'none';
                return;
            }

            loadPropertyValues(currentHlBlockId);
        }

        function loadPropertyValues(hlBlockId) {
            showLoading(valuesContainer, 'Загрузка значений...');
            valuesContainer.style.display = 'block';

            const formData = new FormData();
            formData.append('HL_BLOCK_ID', hlBlockId);
            formData.append('sessid', bitrixSessid);

            fetch('/bitrix/admin/get_property_values.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                valuesContainer.innerHTML = html;
                startUpdateBtn.style.display = 'block';
                priceTypesContainer.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                valuesContainer.innerHTML = '<p style="color: red;">Ошибка загрузки значений</p>';
            });
        }

        function loadPriceDefaults(iblockId) {
            const formData = new FormData();
            formData.append('IBLOCK_ID', iblockId);
            formData.append('sessid', bitrixSessid);

            fetch('/bitrix/admin/kk_price_update_get_defaults.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    return;
                }

                const defaults = data.defaults || {};
                Array.from(priceTypesSelect.options).forEach(option => {
                    option.selected = Object.prototype.hasOwnProperty.call(defaults, option.value);
                });

                priceAdjustments.innerHTML = '';
                Array.from(priceTypesSelect.selectedOptions).forEach(option => {
                    const row = document.createElement('div');
                    row.style.marginBottom = '8px';
                    row.innerHTML = '<label style="display:inline-block; min-width: 230px;">' + option.textContent + ' корректировка:</label>' +
                        '<input type="text" data-price-adjustment=\"' + option.value + '\" value=\"' + (defaults[option.value] || '') + '\" placeholder=\"Например: +1000, -2500, +20%, -10%\" style=\"min-width:280px; padding:4px;">';
                    priceAdjustments.appendChild(row);
                });
                updateAdjustmentMode();
            });
        }

        startUpdateBtn.addEventListener('click', function() {
            if (!updateInProgress) {
                startPriceUpdate();
            }
        });

        function startPriceUpdate() {
            const valuesSelect = document.getElementById('values-select');
            const selectedPriceTypes = Array.from(priceTypesSelect.selectedOptions).map(opt => opt.value);
            if (selectedPriceTypes.length === 0) {
                alert('Выберите хотя бы один тип цены');
                return;
            }

            const priceAdjustmentsMap = {};
            selectedPriceTypes.forEach(typeId => {
                const input = document.querySelector('[data-price-adjustment=\"' + typeId + '\"]');
                priceAdjustmentsMap[typeId] = input ? input.value.trim() : '';
            });
            let selectedValues = valuesSelect ? Array.from(valuesSelect.selectedOptions).map(opt => opt.value) : [];
            
            // Фильтруем пустые значения (опция "- Все значения -")
            selectedValues = selectedValues.filter(value => value !== '');
            
            // Если после фильтрации массив пуст, устанавливаем null чтобы загрузить все значения
            if (selectedValues.length === 0) {
                selectedValues = null;
            }
            
            // Сбрасываем состояние
            updateInProgress = true;
            currentProcessState = {
                currentValueIndex: 0,
                processedElements: 0,
                totalSuccess: 0,
                totalErrors: 0,
                totalSkippedNoPriceUpdate: 0,
                totalOffersSuccess: 0,
                totalOffersProcessed: 0,
                errorFile: null,
                totalValues: 0
            };
            
            consoleLog.style.display = 'block';
            logContent.innerHTML = '<p>Инициализация процесса обновления цен...</p>';
            
            startUpdateBtn.disabled = true;
            startUpdateBtn.textContent = 'Идёт обновление...';

            // Начинаем с шага init
            processStep('init', selectedValues);
        }

        priceTypesSelect.addEventListener('change', function() {
            const selected = Array.from(this.selectedOptions);
            priceAdjustments.innerHTML = '';
            selected.forEach(option => {
                const row = document.createElement('div');
                row.style.marginBottom = '8px';
                row.innerHTML = '<label style="display:inline-block; min-width: 230px;">' + option.textContent + ' корректировка:</label>' +
                    '<input type="text" data-price-adjustment=\"' + option.value + '\" placeholder=\"Например: +1000, -2500, +20%, -10%\" style=\"min-width:280px; padding:4px;\">';
                priceAdjustments.appendChild(row);
            });
            updateAdjustmentMode();
        });

        function updateAdjustmentMode() {
            const isKorsac = currentPropertyMode === 'korsac_default';
            korsacPricingNote.style.display = isKorsac ? 'block' : 'none';
            Array.from(priceAdjustments.querySelectorAll('[data-price-adjustment]')).forEach(input => {
                input.disabled = isKorsac;
            });
            priceAdjustments.style.display = isKorsac ? 'none' : 'block';
        }

        function processStep(step, selectedValues) {
            const formData = new FormData();
            formData.append('IBLOCK_ID', currentIblockId);
            formData.append('PROPERTY_ID', currentPropertyId);
            formData.append('HL_BLOCK_ID', currentHlBlockId);
            formData.append('step', step);
            formData.append('sessid', bitrixSessid);
            const selectedPriceTypes = Array.from(priceTypesSelect.selectedOptions).map(opt => opt.value);
            const priceAdjustmentsMap = {};
            selectedPriceTypes.forEach(typeId => {
                const input = document.querySelector('[data-price-adjustment=\"' + typeId + '\"]');
                priceAdjustmentsMap[typeId] = input ? input.value.trim() : '';
            });
            formData.append('PRICE_TYPES', JSON.stringify(selectedPriceTypes));
            formData.append('PRICE_ADJUSTMENTS', JSON.stringify(priceAdjustmentsMap));

            // Добавляем параметры состояния
            if (step === 'process_value') {
                formData.append('current_value_index', currentProcessState.currentValueIndex);
                formData.append('processed_elements', currentProcessState.processedElements);
                formData.append('total_success', currentProcessState.totalSuccess);
                formData.append('total_errors', currentProcessState.totalErrors);
                formData.append('total_skipped_no_price_update', currentProcessState.totalSkippedNoPriceUpdate);
                formData.append('total_offers_success', currentProcessState.totalOffersSuccess);
                formData.append('total_offers_processed', currentProcessState.totalOffersProcessed);
                formData.append('VALUES', JSON.stringify(selectedValues));
                if (currentProcessState.errorFile) {
                    formData.append('error_file', currentProcessState.errorFile);
                }
            } else if (step === 'init') {
                formData.append('VALUES', JSON.stringify(selectedValues));
            }

            fetch('/bitrix/admin/start_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.json();
            })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Неизвестная ошибка');
                }

                if (step === 'init') {
                    handleInitResponse(response, selectedValues);
                } else if (step === 'process_value') {
                    handleProcessValueResponse(response, selectedValues);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                logContent.innerHTML += '<p style="color: red;">Ошибка: ' + error.message + '</p>';
                finishUpdateProcess();
            });
        }

        function handleInitResponse(response, selectedValues) {
            currentProcessState.totalValues = response.total_values;
            currentProcessState.errorFile = response.error_file;
            
            logContent.innerHTML += '<p>Инициализация завершена. Всего значений для обработки: ' + response.total_values + '</p>';
            logContent.innerHTML += '<p>Начинаем обработку...</p>';
            
            // Начинаем обработку первого значения
            setTimeout(() => {
                processStep('process_value', selectedValues);
            }, 500);
        }

        function handleProcessValueResponse(response, selectedValues) {
            // Добавляем логи текущего значения
            if (response.logs && response.logs.length > 0) {
                response.logs.forEach(log => {
                    if (log.trim() !== '') {
                        const logElement = document.createElement('p');
                        logElement.textContent = log;
                        logContent.appendChild(logElement);
                    }
                });
            }

            // Обновляем состояние
            currentProcessState.currentValueIndex = response.current_value_index;
            currentProcessState.processedElements = response.stats.processed_elements;
            currentProcessState.totalSuccess = response.stats.total_success;
            currentProcessState.totalErrors = response.stats.total_errors;
            currentProcessState.totalSkippedNoPriceUpdate = response.stats.total_skipped_no_price_update || 0;
            currentProcessState.totalOffersSuccess = response.stats.total_offers_success;
            currentProcessState.totalOffersProcessed = response.stats.total_offers_processed;

            // Прокручиваем лог вниз
            consoleLog.scrollTop = consoleLog.scrollHeight;

            if (response.is_completed) {
                // Процесс завершен
                showFinalSummary();
            } else {
                // Продолжаем обработку следующего значения
                currentProcessState.currentValueIndex++;
                
                // Небольшая задержка для избежания таймаута
                setTimeout(() => {
                    processStep('process_value', selectedValues);
                }, 1000);
            }
        }

        function showFinalSummary() {
            const totalElements = currentProcessState.totalSuccess + currentProcessState.totalErrors;
            const totalOffers = currentProcessState.totalOffersProcessed;
            const totalOffersSuccess = currentProcessState.totalOffersSuccess;
            
            logContent.innerHTML += '<p><strong>Результат работы:</strong></p>';
            logContent.innerHTML += '<p>Всего найдено: ' + totalElements + ' товаров, ' + totalOffers + ' ТП</p>';
            logContent.innerHTML += '<p>Успешно обновлено: ' + currentProcessState.totalSuccess + ' товаров, ' + totalOffersSuccess + ' ТП</p>';
            logContent.innerHTML += '<p>С ошибками: ' + currentProcessState.totalErrors + ' товаров, ' + (totalOffers - totalOffersSuccess) + ' ТП</p>';
            logContent.innerHTML += '<p>Пропущено по NO_PRICE_UPDATE: ' + currentProcessState.totalSkippedNoPriceUpdate + ' товаров</p>';
            
            if (currentProcessState.errorFile && currentProcessState.totalErrors > 0) {
                logContent.innerHTML += '<p>Список ID товаров с ошибками: <a href="' + currentProcessState.errorFile + '" target="_blank">' + currentProcessState.errorFile + '</a></p>';
            }
            
            logContent.innerHTML += '<p style="color: green; font-weight: bold;">Обработка завершена!</p>';
            
            finishUpdateProcess();
        }

        function finishUpdateProcess() {
            updateInProgress = false;
            startUpdateBtn.disabled = false;
            startUpdateBtn.textContent = 'Обновить цены';
            
            // Прокручиваем лог вниз
            consoleLog.scrollTop = consoleLog.scrollHeight;
        }

        function showLoading(container, message) {
            container.innerHTML = '<p>' + message + '</p>';
        }

        function resetForm() {
            propertiesContainer.style.display = 'none';
            valuesContainer.style.display = 'none';
            startUpdateBtn.style.display = 'none';
            priceTypesContainer.style.display = 'none';
            consoleLog.style.display = 'none';
            logContent.innerHTML = '';
            updateInProgress = false;
            currentPropertyMode = null;
            updateAdjustmentMode();
        }
    });
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
