# Clarilens Parser: путь к полностью декларативному обходу

Дата: 2026-10-08. Основа: установленный `clarilens/parser` 0.0.1, исходный код приложения и чистый обход двух застройщиков. Это предложения по API, а не описание уже реализованных функций.

## Вывод

Библиотека хорошо решает **разбор одного документа**: XPath/CSS, JSON внутри `<script>`, таблицы, PDF, HTTP и браузерный рендер, проверки полей, происхождение значений. Она пока не исполняет **граф обхода сайта**: получить каталог, пройти проекты, этажи и все страницы выдачи, связать поля одной карточки, нормализовать типы, проверить полноту и сохранить снимок. Именно этот слой сейчас занимает [`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php). Его следует добавить как отдельный декларативный пакет/модуль поверх существующего `DocumentParser`, сохранив простой API разбора одного документа.

## Что уже есть

| Возможность | Подтверждение | Практическая ценность |
|---|---|---|
| JSON-конфиг версии 1, `fromJson()`/`toJson()`, реестр правил | [`ParserConfig`](../vendor/clarilens/parser/src/Config/ParserConfig.php), [`RuleRegistry`](../vendor/clarilens/parser/src/Config/RuleRegistry.php) | Конфиг можно хранить в БД; правила расширяемы, но расширение сейчас требует PHP-фабрику и encoder. |
| XPath, CSS, таблицы, `jsonScript`, PDF | [`README`](../vendor/clarilens/parser/README.md), [`JsonScriptRule`](../vendor/clarilens/parser/src/Extraction/JsonScriptRule.php) | Извлечение скаляров из одного HTML/XML/PDF. |
| HTTP/браузер, allowlist хостов, лимиты | [`SourceConfig`](../vendor/clarilens/parser/src/Source/SourceConfig.php), [`SourceLoader`](../vendor/clarilens/parser/src/Source/SourceLoader.php), [`HttpWebClient`](../vendor/clarilens/parser/src/Source/HttpWebClient.php) | Базовая безопасная загрузка документа. |
| `required`, `checks`, `strict` | [`FieldCheck`](../vendor/clarilens/parser/src/Config/FieldCheck.php), [`DocumentParser`](../vendor/clarilens/parser/src/Parser/DocumentParser.php) | Локальные проверки количества/шаблона/равенства. |
| Значение с DOM/JSON/PDF-адресом и provenance | [`ExtractedValue`](../vendor/clarilens/parser/src/Result/ExtractedValue.php), [`ObservedFactBatch`](../vendor/clarilens/parser/src/Result/ObservedFactBatch.php) | Аудит отдельных фактов. `complete` уже есть, но его выставляет вызывающий код. |

## Доказанные ограничения на нашем обходе

Чистый прогон: 39 успешных проектов, 4 623 предложения, 3 648 файлов планировок. У Arm Construct три проекта помечены недоступными из-за неполной страницы выдачи; у 1 360 предложений статус `unknown`. Это **не дефект XPath-движка**: приложение применяет неполный селектор и не умеет повторять `loadMore`.

**Происхождение чисел и воспроизводимость.** В текущей БД после одного чистого прогона: Metrum 35 успешных проектов и 3 240 предложений; Arm Construct 4 успешных проекта и 1 383 предложения. Итого 39 и 4 623. Эти значения — наблюдение живого сайта, не стабильные assertions для CI. В репозитории нет сохранённых полных HTML/JSON-снимков обоих сайтов. Тесты содержат только небольшие HTML/JSON-строки в коде: [`ArmConstructProjectSourceTest`](../tests/Infrastructure/ArmConstructProjectSourceTest.php) моделирует две карточки с `special-offer`, а [`CrawlDeveloperTest`](../tests/Application/CrawlDeveloperTest.php) — плоский каталог. Они не воспроизводят `loadMore`, вложенные `child_projects` и пропущенный `sold-out`. Во время анализа скачаны два временных HTML-файла вне репозитория (`/private/tmp/arm-floor-page.html`, `/private/tmp/arm-unit-341.html`); они доказывают разметку, но не воспроизводят браузерный клик без сети/мока Livewire.

**Уточнение структуры и ключей.** Дополнительно загруженный временный снимок Metrum (`/private/tmp/metrum-home-current.html`) содержит 9 верхнеуровневых и 26 дочерних проектов, глубина 2, без повторов ID/slug в этом снимке. Это не доказательство произвольной глубины или глобальной уникальности на будущих снимках. Код рекурсивно обходит `child_projects` без лимита и перезаписывает повторный ID в словаре ([`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php)). Текущие ключи БД — `(developer, source_id)` отдельно для projects и apartments ([`Project`](../src/Entity/Project.php), [`Apartment`](../src/Entity/Apartment.php)); в Metrum 33 значения встречаются одновременно как ID проекта и предложения, поэтому тип сущности обязателен в идентификаторе. У Arm Construct ID этажа повторяется между проектами: 123 уникальных `floor_id` при 137 парах `(project_id, floor_id)` в текущей БД.

**Обновление fixtures.** Добавлены [обезличенные структурные фрагменты](../tests/Fixtures/parser/README.md) для вложенного каталога, неполной страницы с `loadMore` и статуса `sold-out`. Они сохраняют форму наблюдавшейся разметки, но используют вымышленные значения и не являются полными снимками сайта. Браузерный ответ Livewire и последовательность кликов по-прежнему не зафиксированы.

**Размеры двух сохранённых Metrum ответов (байты, один момент наблюдения).** Каталог: HTML 357 766; `:projects` 25 864 в HTML и 17 454 после HTML entity decode. Tsaghkadzor Park: HTML 2 179 089; `:project` 55 097/45 733, `:buildings` 100 387/73 481, `:all-building-apartments` 656 509/464 829. Это размеры конкретных ответов, не максимумы для всех проектов. Нынешний лимит HTML в [`DocumentParser`](../vendor/clarilens/parser/src/Parser/DocumentParser.php) — 5 МБ; этот пример проходит, но запас нельзя считать достаточным без измерения всего каталога. Дополнительно подготовлены [синтетические случаи повторных ключей](../tests/Fixtures/parser/README.md): одинаковые, конфликтующие и между этажами.

1. На странице этажа Komitas 60 видно `29 Предложение` и кнопку `wire:click.prevent="loadMore"`; наш обход выбрасывает исключение, если число ссылок меньше счётчика ([`ConfiguredProjectSource`, проверка выдачи](../src/Infrastructure/Parsing/ConfiguredProjectSource.php)). Библиотека умеет до пяти однократных `click`, но не `repeat until button absent/count reached` ([`BrowserAction`](../vendor/clarilens/parser/src/Source/BrowserAction.php), [`render-page.ts`](../vendor/clarilens/parser/scripts/render-page.ts)). Нужен ограниченный декларативный цикл.
2. У проданной квартиры Five Towers `wire:name="manager.apartment-status"` содержит `<span class="sold-out">Продано</span>`. Наше правило ищет только `span.special-offer` ([миграция с конфигом](../migrations/Version20261008190329.php)); пропущенное значение преобразуется в `unknown` ([`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php)). Нужны альтернативные селекторы и строгая проверка доли неизвестных статусов. Даже сегодня это можно исправить XPath `//span[contains(@class,"special-offer") or contains(@class,"sold-out")]` в БД без изменения библиотеки.
3. Metrum хранит JSON в атрибутах `:projects`, `:project`, `:buildings`, `:all-building-apartments`; приложение отдельно вырезает атрибут, декодирует JSON и рекурсивно обходит `child_projects` ([`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php)). `jsonScript` принимает только `<script>` и отдаёт только скаляры, хотя его JSON pointer полезен ([`JsonScriptRule`](../vendor/clarilens/parser/src/Extraction/JsonScriptRule.php)). Нужен источник JSON из атрибута и структурированный результат.
4. Даже для Arm Construct приложение само строит URL фильтров, проходит этажи, сопоставляет карточку с этажом, нормализует статусы и параметры, качает картинки ([`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php), [`PlanImageStore`](../src/Infrastructure/Storage/PlanImageStore.php)). `ParserConfig` описывает один `source`, список правил и таблиц, но не стадии обхода ([`ParserConfig`](../vendor/clarilens/parser/src/Config/ParserConfig.php), [`DocumentParser`](../vendor/clarilens/parser/src/Parser/DocumentParser.php)).

## Предлагаемый API, по приоритету

### P0. Версионированный `CrawlConfig` и граф стадий

Новый корневой документ, например `crawlVersion: 1`, `start`, `stages`, `output`, `limits`. Каждая стадия: входной URL/документ, parser rules, повторяемые записи, переходы к следующей стадии, правила проверки. Стадии должны ссылаться на данные предшественника через **ограниченные пути**, без произвольного PHP/JS. Типовые узлы: `fetch`, `extract`, `forEach`, `follow`, `repeat`, `map`, `emit`. Исполнитель должен вести контекст `developer → project → building → floor → offer` и сохранять родительский ключ. Отдельный `DocumentParser` остаётся пригодным для одного документа.

Почему P0: пока приложение содержит разветвление `embedded_json`/`linked_pages`, добавление третьего типа сайта снова потребует PHP-кода. Схему DSL надо валидировать до первого HTTP-запроса, привязать к версии и явно отклонять неизвестные ключи. Референс: [`ParserConfig::fromJson()`](../vendor/clarilens/parser/src/Config/ParserConfig.php) уже задаёт паттерн для версионирования.

### P0. Повторяемый обход и доказательство полноты

Поддержать три механизма: `nextLink` (обычная пагинация), `pageParameter` (номер/offset/cursor), `browserRepeat` (клик по кнопке до исчезновения). Для Livewire нужен сценарий `click [wire\\:click*="loadMore"]`, дождаться роста числа карточек, остановиться, когда кнопки нет или достигнут ожидаемый счётчик. Обязательные пределы: `maxPages`, `maxClicks`, `maxItems`, `maxDuration`, дедупликация URL/ID и детектор отсутствия прогресса. `ready.selector` сейчас лишь ждёт появления элемента, а действия выполняются последовательно по одному разу ([`SourceConfig`](../vendor/clarilens/parser/src/Source/SourceConfig.php), [`render-page.ts`](../vendor/clarilens/parser/scripts/render-page.ts)).

Результат обхода должен включать `coverage`: обнаружено/прочитано страниц и элементов, ожидаемый счётчик, причины пропусков и `complete`. Полноту нельзя вывести из отсутствия ошибок разбора: [`ObservedFactBatch::complete`](../vendor/clarilens/parser/src/Result/ObservedFactBatch.php) сегодня задаёт вызывающий код. При неполном покрытии публикация снимка должна блокироваться, чтобы старые предложения не ошибочно стали `missing`.

### P0. Структурированные записи с контекстом, а не плоский список строк

Добавить `records`/`repeatWithin` с селектором контейнера и полями относительно него; `id`, `url`, `status`, `price`, `images` одного offer должны остаться одной записью. Для JSON нужны выбор массивов/объектов, рекурсивный `children`, JSON pointer каждого поля и связь с родителем. Сейчас `XPathRule` и `JsonScriptRule` отдают независимые `ExtractedValue`, а `JsonScriptRule` отвергает нескалярный итог ([`XPathRule`](../vendor/clarilens/parser/src/Extraction/XPathRule.php), [`JsonScriptRule`](../vendor/clarilens/parser/src/Extraction/JsonScriptRule.php)). Простое выравнивание нескольких списков по индексу ненадёжно: отсутствующий DOM-узел сместит остальные поля.

### P0. Безопасный набор преобразований и альтернатив

Декларативные операции: `firstNonEmpty` по нескольким XPath/CSS/JSON-путям; `trim`, нормализация пробелов и регистра; `regexCapture`; `replace`; `parseDecimal` с локалью/валютой; `parseInt`; `mapEnum` с синонимами; `absoluteUrl`; `querySet`; `template` с URL-encoding; `default` только для явно допустимого поля. Для статуса: `mapEnum` + `onUnknown: fail|quarantine`, не молчаливое `unknown`. Для Metrum: `jsonAttribute` + `decodeHtmlEntities` + `jsonPath`. Сейчас нормализация текста есть в [`DomValue`](../vendor/clarilens/parser/src/Extraction/DomValue.php), но составные преобразования написаны в приложении.

Сделать трансформации типизированными: исходное и нормализованное значения хранить вместе; ошибки с путём конфигурации и источника. Не разрешать произвольные выражения, eval, шаблоны с доступом к объектам PHP или секретам.

### P1. Контроль качества снимка

Добавить checks на уровне записи, страницы и запуска: обязательные `id`/`url`/`status`, уникальность ID, допустимый домен ссылок, `expectedCount == uniqueItems`, `unknownStatusRatio <= threshold`, `imageCoverage >= threshold`, `projectCount >= priorCount × threshold`; сравнение с прошлым успешным снимком и допустимый порог изменений. Существующие `FieldCheck` проверяют отдельное поле в одном документе ([`FieldCheck`](../vendor/clarilens/parser/src/Config/FieldCheck.php)); они не выражают связь между счётчиком выдачи, количеством карточек и итоговой БД. Нужны причины `complete=false` и отчёт по каждой стадии.

### P1. Унифицированная загрузка и политика безопасности

Вынести в config HTTP-метод для разрешённых запросов, заголовки/User-Agent, локаль, таймауты, интервалы, retries с backoff, лимиты конкурентности и размеров, response type HTML/JSON/PDF. Для браузера — ожидание сети/роста элементов и извлечение финального DOM после действий. Для картинок — `asset`-поле с allowlist MIME/домена, размером, SHA-256, URL и статусом скачивания. Сейчас приложение само создаёт HttpClient и отдельно скачивает изображения ([`ConfiguredProjectSource`](../src/Infrastructure/Parsing/ConfiguredProjectSource.php), [`PlanImageStore`](../src/Infrastructure/Storage/PlanImageStore.php)); библиотечный HTTP-клиент допускает только одиночный GET ([`HttpWebClient`](../vendor/clarilens/parser/src/Source/HttpWebClient.php)).

Сохранить нынешнюю защиту: allowlist хостов, запрет приватных сетей/редиректов, лимиты размера и времени ([`HttpWebClient`](../vendor/clarilens/parser/src/Source/HttpWebClient.php), [`SourcePolicy`](../vendor/clarilens/parser/src/Source/SourcePolicy.php)). Для генерируемых URL проверять хост **после** подстановки, а не только стартовый адрес. Браузерные циклы должны иметь верхнюю границу.

### P1. Версионирование, предварительная проверка и воспроизводимость

`compile(config)` должен проверять ссылки на стадии, типы полей, шаблоны URL, селекторы, ограничения обхода, allowlist; выдавать человекочитаемые ошибки. `dryRun` на сохранённых HTML/JSON fixtures и `explain` с выборкой URL, значений, provenance и причинами отклонения. Конфиг в БД хранить иммутабельными ревизиями: `definition`, `version`, `hash`, `createdAt`, `activatedAt`; каждый запуск записывает hash конфигурации и версии библиотеки. `ParserConfig` и `ObservedFactBatch` уже версионированы, но не дают связи снимка с конкретной ревизией конфигурации ([`ParserConfig`](../vendor/clarilens/parser/src/Config/ParserConfig.php), [`ObservedFactBatch`](../vendor/clarilens/parser/src/Result/ObservedFactBatch.php)).

### P2. Инкрементальность и публикация

Поддержать `ETag`/`Last-Modified`, кэш на URL, checkpoint/resume, идемпотентный `runId` и явную публикацию только полного снимка. Это особенно важно для тысяч карточек и ежедневного обхода. Состояние обхода и публикация в PostgreSQL/очередь могут оставаться интеграцией приложения, но библиотека должна выдавать типизированный `CrawlOutcome` с записями, покрытиями, ошибками и активами. Планировщик cron, Doctrine-сущности и Twig не относятся к ядру парсера.

## Эскиз декларативного конфига

Ниже **предлагаемый**, сейчас не поддерживается библиотекой. Показывает общий синтаксис для страницы с кнопкой `loadMore`:

```yaml
crawlVersion: 1
start: {url: 'https://armconstruct.am/ru', allowHosts: ['armconstruct.am']}
limits: {maxPages: 500, maxItems: 20000, maxDurationSeconds: 3600, concurrency: 8}
stages:
  projects:
    fetch: {render: http}
    records:
      select: 'a[href*="/ru/projects/"]'
      fields:
        url: {from: '@href', transforms: [absoluteUrl]}
        id: {from: '$url', transforms: [{regexCapture: '~/projects/([^/]+)$~'}]}
    follow: {stage: project, url: '$record.url'}
  project:
    fields: {title: {xpath: '(//h1)[1]', required: true}}
    records:
      select: 'a[href*="/ru/filters?"]'
      fields: {url: {from: '@href', transforms: [absoluteUrl]}}
    follow: {stage: floor, url: '$record.url'}
  floor:
    fetch:
      render: browser
      repeat:
        click: 'button[wire\\:click*="loadMore"]'
        until: {selectorAbsent: 'button[wire\\:click*="loadMore"]'}
        waitFor: {itemCountIncrease: '.project-appartment-footer-title'}
        maxClicks: 50
    expectedCount: {regexFrom: '//*[contains(., "Предложение")]', pattern: '/(\\d+)\\s*Предложен/u'}
    records:
      select: '.project-appartment-footer-title'
      fields: {url: {from: '@href', transforms: [absoluteUrl]}}
    checks: [{uniqueCountEquals: '$expectedCount'}]
    follow: {stage: offer, url: '$record.url'}
  offer:
    fields:
      id: {from: '$url', transforms: [{regexCapture: '~/([0-9]+)$~'}], required: true}
      status:
        firstNonEmpty:
          - {xpath: '//*[@wire:name="manager.apartment-status"]//span[contains(@class,"special-offer")]'}
          - {xpath: '//*[@wire:name="manager.apartment-status"]//span[contains(@class,"sold-out")]'}
        transforms: [{mapEnum: {available: ['доступно'], reserved: ['бронь'], sold_out: ['продано']}}]
        onUnknown: fail
      plan: {xpath: '//img[@data-tab="scheme"]/@src', transforms: [absoluteUrl], asset: {maxBytes: 10000000}}
output: {entity: offer, key: '$offer.id', completeOnly: true}
```

Для Metrum те же стадии можно заполнить `jsonAttribute`/`jsonPath`/`forEach` без PHP-обхода дерева. Синтаксис выше надо уточнить по результатам прототипа на сохранённых страницах и JSON Schema; важнее семантика, чем конкретные имена ключей.

## Порядок реализации и критерии готовности

1. **P0, компилятор и результат:** `CrawlConfig` v1, типизированные стадии/записи, `CrawlOutcome`, лимиты и ошибки с путём конфигурации. Проверить компиляцию обоих текущих конфигов без site-specific PHP.
2. **P0, Metrum:** `jsonAttribute`, структурированные JSON records, рекурсивный `children`, подстановка URL. Критерий: 35 проектов, 3 240 предложений на контрольном снимке; все значения связаны с проектом и этажом.
3. **P0, Arm Construct:** относительные DOM records, `follow`, параметризация URL, `browserRepeat`, ожидаемые счётчики. Критерий: все 7 проектов завершены или имеют явную причину source failure; никакой неполный снимок не публикуется.
4. **P0, статусы:** альтернативы и `mapEnum`, строгий check. Критерий: для сохранённых карточек `Доступно`/`Продано`/брони результат детерминирован, `unknown` не проходит публикацию.
5. **P1, эксплуатация:** активы, ревизии конфига, dry-run/explain, контроль качества снимка, ограниченная конкурентность. Критерий: повторный обход воспроизводим по зафиксированным fixtures, лог указывает точную стадию и URL потери.

Разделить тесты на unit для операций DSL, контрактные на сохранённых документах каждого сайта и end-to-end на полном обходе с проверкой покрытия. Сеть не должна быть обязательной для регрессии конфигурации.
