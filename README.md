# Conector Vtex para Vega

### Descripción

Se implementan los flujos de implementación para Invoices, Orders, Price y 
Stock para la plataforma Vtex. Además, se implementa la configuración del Feed v3 para recibir órdenes.

### Instalación

```
composer require vega/connector-vtex
```

### Configuración

* `site`: Corresponde al código de tienda de Vtex. Generalmente se pede extraer
  de la url https://<site>.vtexcommercestable.com.br provista por Vtex. Sin 
  valor por defecto.
* `app_key`: Clave de identificación del token de autenticación. Sin valor por 
  defecto.
* `app_token`: Token secreto de autenticación. Sin valor por defecto.
* `base_uri`: Plantilla para la URL del ambiente. La variable `{{site}}` es 
  reemplazada por el valor definido en el setting `site`. Valor por defecto 
  `'https://{{site}}.vtexcommercestable.com.br'`.
* `orders_mode`: Modo de obtención de órdenes en dicha integración. Valor por defecto:
  `pagination`, configuración por la cual las órdenes se obtienen paginando la API
   de `List orders`. Otro valor posible: `feed`, valor con el cual las órdenes se obtienen
   mediante la API `feed v3`. Para configurar esta forma de obtención es necesario haber 
   ejecutado al menos una vez la integración de **Feed - Config** exitosamente.
* `mongo_composite_keys`: Array de nombres de campos a tener en cuenta en las integraciones
 para formar parte de la clave única que se almacena en __MongoDB__. Estos campos ayudarían al
correcto conteo de registros en caso de que se repita en la integración la clave (sku, por ej)
Posibles campos a incluir en el array: `seller`, `warehouse`.
* `subaccounts.subaccount_key`: Nombre del campo utilizado para los sellers. Valor por defecto: `seller`. 
* `subaccounts.marketplace_value`: valor a tomar como marketplace en el campo "seller" de las integraciones. Valor por defecto: `marketplace`. 
* `entities`: Las entidades disponibles para el conector son `orders`, `order`,
  `invoices`, `stock`, `catalog`, `sku`, `price`, `log`, `rules`, `email` y `feed`.
* `entities.orders.get.confirm`: Controla el comportamiento opcional de 
  confirmación de pedido posterior a la bajada de la integración 
  *Orders - Get*. Valor por defecto: `false`.
* `entities.orders.get.use_subaccounts`: Controla si el proceso de órdenes busca órdenes de posibles
 sellers configurados. Valor por defecto: `false` 
* `entities.invoices.create.status_ready`: Estado del pedido que indica que 
  está disponible para ser preparado. No se utiliza actualmente. Valor por 
  defecto `'ready-for-handling'`.
* `entities.invoices.create.status_start_handling`: Estado del pedido que 
  indica que está siendo preparado. Valor por defecto `'start-handling'`.
* `entities.invoices.create.status_handling`: Estado del pedido que indica que
  está siendo preparado. Valor por defecto `'handling'`.
* `entities.invoices.create.status_invoiced`: Estado del pedido que indica que
  está facturado. Valor por defecto `'invoiced'`.
* `entities.invoices.get.use_subaccounts`: Controla si el proceso de invoices busca órdenes de posibles
 sellers configurados. Valor por defecto: `false` 
* `entities.invoices.create.validations`: Opcional. Nombre del archivo de validaciones de campos de registro. Ver más abajo.   
* `entities.feed.config.data`: Contiene el conjunto de valores necesarios para configurar
  el `feed v3` de órdenes:
    * `type`: filtro de tipo. Utilizamos el valor por defecto `FromWorkflow` que indica que 
    recibiremos órdenes que cambien de estado.
    * `status`: array de estados de órdenes que vamos a obtener en el feed. Por defecto configurado
    para recibir las `ready-for-handling`.
    * `expression`: campo no utilizado, necesario solamente para filtrar en el 
    type `FromOrders` que no usamos.
    * `disableSingleFire`: campo enviado en "false", que en realidad solamente es necesario 
    en el type `FromOrders` que no usamos.
    * `visibilityTimeoutInSeconds`: cantidad de segundos en los que el pedido no estará disponible
     en el feed una vez que fue enviado, y a la espera de la confirmación de procesado. 
     Valor por defecto: `250`
    * `MessageRetentionPeriodInSeconds`: cantidad de segundos de tiempo de vida de una orden en el feed,
     luegos de los cuales deja de estar disponible. Valor por defecto: `345600`
* `entities.feed.get.maxcalls`: cantidad máxima de llamadas al **Get Feed** por proceso de órdenes.
Valor por defecto: `15`
* `entities.stock.update.use_subaccounts`: Controla si el proceso de stock incluye productos de posibles
 sellers configurados. Valor por defecto: `false` 
* `entities.stock.update.validations`: Opcional. Nombre del archivo de validaciones de campos de registro. Ver más abajo.
* `entities.price.update.use_subaccounts`: Controla si el proceso de precios busca productos de posibles
 sellers configurados. Valor por defecto: `false` 
* `entities.price.update.validations`: Opcional. Nombre del archivo de validaciones de campos de registro. Ver más abajo.

### Integraciones

#### Invoices - Create

Se trata de un flujo de dirección **outbound** en el que se leen los 
identificadores de pedido almacenados en el Data Layer para determinar y 
ejecutar las acciones necesarias para la facturación de los pedidos en el 
ambiente de Vtex.

##### Proceso

Se recorren los identificadores de pedido del Data Layer. Para cada uno, se 
obtiene el detalle del pedido consultando el endpoint 
[Get order](https://developers.vtex.com/vtex-rest-api/reference/orders#getorder)
y se le aplican los modelos de
[Order Item](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/OrderItem.json)
para simplificar las líneas de pedido y el de
[Order](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Order.json)
para simplificar la estructura general del pedido resultante. Luego se aplican
también las transformaciones de
[Order Item](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Transformations/OrderItem.json)
y [Order](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Transformations/Order.json)
para normalizar el formato de los valores de montos y fechas.

Se evalúa si el pedido ya fue facturado, en cuyo caso, se marca el registro
como unchanged y se interrumpe el procesamiento del mismo.

Si el pedido no fue facturado previamente, se determina si el mismo debe ser 
marcado como `start-handling` y en caso de ser necesario, se lo  actualiza 
llamando al endpoint de [Start handling order](https://developers.vtex.com/vtex-rest-api/reference/orders#starthandling)
utilizando el modificador payload
[Confirm](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Confirm.json).

Finalmente se invoca el endpoint para la generación de la factura 
[Order invoice notification](https://developers.vtex.com/vtex-rest-api/reference/invoice9)
aplicando el modificador payload 
[Invoice](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Invoice.json). 

#### Orders - Get

Se trata de un flujo de dirección **inbound** en el que se obtienen pedidos del
ambiente de Vtex, se los procesa y se los almacena en el Data Layer de Vega.

##### Proceso

Dependiendo de la configuración de `orders_mode`, se realiza o bien una serie de consultas 
(como máximo el valor de config de `entities.feed.get.maxcalls`) a la API de
 [Get Feed](https://developers.vtex.com/vtex-rest-api/reference/feed-v3#getfeedorderstatus1),
si es que está configurado el modo en `feed`, o bien una consulta de pedidos disponibles al endpoint de 
[List orders](https://developers.vtex.com/vtex-rest-api/reference/orders#listorders) 
e itera sobre todas las páginas del resultado. 
En esta etapa se aplican los 
modificadores del 
[modelo](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Orders.json) 
para reducir el resultado a la mínima expresión y 
[transformaciones](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Transformations/Orders.json)
para normalizar los valores numéricos y fechas.

Cada pedido obtenido es consultado individualmente al endpoint de 
[Get order](https://developers.vtex.com/vtex-rest-api/reference/orders#getorder) 
para obtener el detalle del mismo y se le aplican los modelos de 
[Order Item](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/OrderItem.json)
para simplificar las líneas de pedido y el de 
[Order](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Order.json)
para simplificar la estructura general del pedido resultante. Luego se aplican 
también las transformaciones de 
[Order Item](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Transformations/OrderItem.json)
y [Order](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Transformations/Order.json) 
para normalizar el formato de los valores de montos y fechas.

Opcionalmente, por cada item dentro de order, puede obtenerse el detalle del producto, configurando el parámetro booleano `entities.orders.get.get_sku_data_by_id` en true. A los datos del producto se le aplica el modelo de [Skudata](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Skudata.json) para simplificar la estructura y la misma se guarda dentro del dato de item.

Por último, se utiliza el servicio no documentado de Conversation Tracker para 
mapeo del alias de correo electrónico generado por Vtex a la cuenta de email 
real del cliente. El resultado se procesa con el modificador de modelo 
[Email](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Email.json).

La información resultante es almacenada en el Data Layer.

Opcionalmente, el pedido es luego confirmado a Vtex mediante la invocación del servicio 
[Start handling order](https://developers.vtex.com/vtex-rest-api/reference/orders#starthandling)
utilizando el modificador payload 
[Confirm](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Confirm.json).

En caso de utilizar el modo `feed`, el proceso habrá almacenado los id de órdenes y 
un hash `handle` que vino asociado a la orden desde el feed, y habrá colocado en un array de `handles` 
los que se hubieran almacenado correctamente en el data layer. En este punto, enviará a Vtex una confirmación
de los handles procesados invocando la API [Commit Feed](https://developers.vtex.com/vtex-rest-api/reference/feed-v3#commititemfeedorderstatus)
utilizando el payload [Feed](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Feed.json).

#### Price - Update

Se trata de un flujo en dirección **outbound** en el que se leen productos 
indicados por RefId (código del SKU) y se actualiza la definición existente en 
la tabla de precios.

##### Proceso

Se recorren las entradas del Data Layer y se consume el servicio 
[Get SKU by RefId](https://developers.vtex.com/vtex-rest-api/reference/catalog-api-sku#catalog-api-get-sku-refid)
para obtener los identificadores de SKU de Vtex y se los mapea con el 
modificador de modelo 
[Skudata](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Skudata.json).

Con este identificador se consulta la información actual de precios invocando 
[Price by skuId (legacy - v1)](https://developers.vtex.com/vtex-rest-api/reference/prices-legacy-v1#pricebyskuid)
y se procesa el resultado con el modificador de modelo de [Price](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Price.json).

Finalmente se utiliza el ID obtenido del listado de precios para enviar una 
actualización mediante 
[Save price (legacy - v1)](https://developers.vtex.com/vtex-rest-api/reference/prices-legacy-v1#saveprice)
modificado por el payload [Price](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Price.json).

#### Stock - Update

Se trata de un flujo en dirección **outbound** en el que se leen productos
indicados por RefId (código del SKU) y se actualiza el stock físico de los 
mismos.

##### Proceso

Se recorren las entradas del Data Layer y se consume el servicio
[Get SKU by RefId](https://developers.vtex.com/vtex-rest-api/reference/catalog-api-sku#catalog-api-get-sku-refid)
para obtener los identificadores de SKU de Vtex y se los mapea con el
modificador de modelo
[Skudata](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Models/Skudata.json).

Con este identificador se actualiza el stock de cada producto mediante la 
invocación del método no documentado **Inventory Set Balance** utilizando el payload 
[Stock](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/Stock.json).

#### Feed - Config

Integración utilizada para configurar el feed que se recibirá en la integración de **Orders - Get**
si se setea el `orders_mode` en modo `feed`. Se debería ejecutar solamente para inicializar
el feed o en caso de querer modificar la lista de estados de órdenes que queremos recibir, 
o los segundos de retención/tiempo de vida de la orden.

##### Proceso

Se invoca la API [Config Feed](https://developers.vtex.com/vtex-rest-api/reference/feed-v3#feedconfiguration)
de Vtex, tomando la configuración de `entities.feed.config.data`, y pasando por el
modificador payload [FeedConfig](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Modifiers/Payloads/FeedConfig.json). 

### Validaciones

Como se mencionó en el apartado de configuración, existe la posibilidad de validar los registros
recibidos en las integraciones de invoices, stock y price, para así evitar procesar registros que no tengan
los campos que se esperan en la validación. Para esto se utiliza el módulo `vega/validation`.
Eĺ valor a configurar en caso de querer validar, es el nombre del archivo donde están las configuraciones de validaciones.
En caso de configurar la clave `validations` en alguno de estos, se validará contra ese archivo.
La ruta para los archivos es: Connectors/Vtex/\[Entidad\]/Validations (donde la Entidad será: Stock, Price o Invoices).
Archivo de ejemplo [aquí](https://bitbucket.org/lyracons/vega-connector-vtex/src/master/src/Validations/Stock.json.example)


Ver también [CHANGELOG.md](changelog.md)

