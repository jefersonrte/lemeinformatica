ALTER TABLE cotacoes MODIFY canal_envio ENUM('email','whatsapp','manual','ambos') DEFAULT 'email';
