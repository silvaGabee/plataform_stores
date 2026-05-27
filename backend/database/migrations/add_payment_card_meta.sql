-- Metadados do cartão (apenas últimos 4 dígitos — nunca armazenar número completo ou CVV)
ALTER TABLE payments
    ADD COLUMN card_holder VARCHAR(120) DEFAULT NULL AFTER pix_qr_code,
    ADD COLUMN card_last4 CHAR(4) DEFAULT NULL AFTER card_holder,
    ADD COLUMN card_brand VARCHAR(20) DEFAULT NULL AFTER card_last4;
