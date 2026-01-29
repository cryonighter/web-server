# Конфигурация веб-сервера

Конфигурация веб-сервера хранится в XML-файле.
По умолчанию конфигурационный файл ищется в директории `./config/` и называется [global.xml](../config/global.xml).

## Пример конфигурации

### Базовая конфигурация

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<global>
   <requestSizeMax>16777216</requestSizeMax>
</global>
```

### Конфигурация prefork модели

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<global>
   <requestSizeMax>16777216</requestSizeMax>

   <prefork>
      <workerCount>4</workerCount>
      <workerRequestLimit>1000</workerRequestLimit>
   </prefork>
</global>
```

## Структура конфигурационных файлов

### Элемент `<global>`

Корневой элемент, с которого начинается конфигурация.
```xml
<global></global>
```

## Основные директивы `<global>`

### Элемент `<requestSizeMax>`

Максимальный размер запроса в байтах, который может быть обработан сервером.

```xml
<requestSizeMax>16777216</requestSizeMax>
```

**По умолчанию:** `16777216` (16 МБ)

### Элемент `<prefork>`

Содержит настройки prefork модели работы веб-сервера.

```xml
<prefork>
   <workerCount>4</workerCount>
   <workerRequestLimit>1000</workerRequestLimit>
</prefork>
```

**По умолчанию:** отсутствует.

Состоит из нескольких поддиректив:
`<workerCount>` - Количество воркеров. По умолчанию: `4`.
`<workerRequestLimit>` - Максимальное количество запросов, которое может обработать воркер. По умолчанию: `1000`.
