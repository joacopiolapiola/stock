DROP DATABASE viernes;
CREATE DATABASE IF NOT EXISTS viernes;
USE viernes;

CREATE TABLE productos(
    id INT PRIMARY KEY,
    precio INT,
    nombre VARCHAR(20)
);

CREATE TABLE cambios(
    id INT PRIMARY KEY,
    producto INT,
    tipo ENUM('compra','venta') NOT NULL,
    cantidad INT,
    subtotal INT,
    sujeto VARCHAR(20),
    dia TIMESTAMP UNIQUE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto)
        REFERENCES productos(id)
);

CREATE TABLE cantidades(
    producto INT PRIMARY KEY,
    cantidad INT,
    FOREIGN KEY (producto)
        REFERENCES productos(id)
);

CREATE TABLE owners(
nombre VARCHAR(20) PRIMARY KEY,
pass VARCHAR(90)
);

CREATE TABLE vars(
    ganancia FLOAT(6,2)
);

INSERT INTO owners(nombre,pass) VALUES ('joaco','$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km');
--123