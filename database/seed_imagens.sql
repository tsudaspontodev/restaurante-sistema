-- Opcional: fotos reais para os produtos de exemplo do database.sql
-- (imagens do Unsplash, uso livre). Só altera produtos que ainda usam placeholder.
USE restaurante_db;

UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Classic Smash Burger' AND imagem_url LIKE '%placehold.co%';
UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Bacon BBQ Duplo' AND imagem_url LIKE '%placehold.co%';
UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Batata Rústica com Alecrim' AND imagem_url LIKE '%placehold.co%';
UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Suco Natural de Laranja 500ml' AND imagem_url LIKE '%placehold.co%';
UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Refrigerante Lata 350ml' AND imagem_url LIKE '%placehold.co%';
UPDATE produtos SET imagem_url = 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&h=450&fit=crop&auto=format&q=70'
WHERE nome = 'Brownie com Sorvete' AND imagem_url LIKE '%placehold.co%';
