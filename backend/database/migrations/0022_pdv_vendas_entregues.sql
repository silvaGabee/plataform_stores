-- Venda de balcão (PDV) é entregue na hora: o cliente sai com a mercadoria.
--
-- Até aqui a confirmação do pagamento não mexia em delivery_stage, então toda
-- venda do PDV ficava como 'solicitado' e aparecia no quadro de Entregas ao
-- lado dos pedidos da vitrine. O OrderService agora marca 'entregue' ao
-- confirmar; esta migration corrige as vendas antigas.

UPDATE orders SET delivery_stage = 'entregue'
 WHERE order_type = 'pdv' AND status = 'pago' AND delivery_stage <> 'entregue';
