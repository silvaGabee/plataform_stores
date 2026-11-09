-- Remove o status 'enviado' de orders.status.
--
-- Nenhuma tela ou rota gravava esse valor, mas o BI, as avaliações e o
-- comprovante ainda o liam. Pagamento e entrega são coisas diferentes: o
-- status diz se o pedido foi pago; o andamento da entrega (empacotando,
-- transportadora, em rota, entregue) fica em orders.delivery_stage.
--
-- Algum pedido antigo com 'enviado' foi pago, então vira 'pago' antes de o
-- valor sair do ENUM (senão o MySQL o trocaria por '' em modo não estrito).

UPDATE orders SET status = 'pago' WHERE status = 'enviado';

ALTER TABLE orders
  MODIFY `status` ENUM('pendente','pago','cancelado','estornado') NOT NULL DEFAULT 'pendente';
