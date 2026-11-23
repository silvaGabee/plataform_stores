-- Pedido de retirada só tem as etapas "solicitado" e "entregue".
--
-- Antes da validação no servidor (OrderApiController::updateDeliveryStage),
-- um pedido de retirada podia ficar em etapa de entrega ("empacotando",
-- "entregue_transportadora", "em_rota"). O quadro de retirada não tem essas
-- colunas e o pedido pago sumia da tela de Entregas. Aqui ele volta para
-- "solicitado", de onde o painel o marca como retirado.
--
-- Idempotente: depois de rodar, nenhuma linha casa mais.
UPDATE orders
   SET delivery_stage = 'solicitado'
 WHERE delivery_type = 'retirada'
   AND delivery_stage IN ('empacotando', 'entregue_transportadora', 'em_rota');
