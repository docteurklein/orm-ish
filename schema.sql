--
-- PostgreSQL database dump
--

-- Dumped from database version 10.1
-- Dumped by pg_dump version 10.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: attribute; Type: TABLE; Schema: public; Owner: florian
--

CREATE TABLE attribute (
    attribute_id uuid NOT NULL,
    family_id uuid
);


ALTER TABLE attribute OWNER TO florian;

--
-- Name: product; Type: TABLE; Schema: public; Owner: florian
--

CREATE TABLE product (
    product_id uuid NOT NULL
);


ALTER TABLE product OWNER TO florian;

--
-- Name: product_attribute; Type: TABLE; Schema: public; Owner: florian
--

CREATE TABLE product_attribute (
    product_id uuid NOT NULL,
    attribute_id uuid NOT NULL
);


ALTER TABLE product_attribute OWNER TO florian;

--
-- Name: attribute attribute_pkey; Type: CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY attribute
    ADD CONSTRAINT attribute_pkey PRIMARY KEY (attribute_id);


--
-- Name: product product_pkey; Type: CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY product
    ADD CONSTRAINT product_pkey PRIMARY KEY (product_id);


--
-- Name: product_attribute unique_assoc; Type: CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY product_attribute
    ADD CONSTRAINT unique_assoc PRIMARY KEY (product_id, attribute_id);


--
-- Name: attribute attribute_family_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY attribute
    ADD CONSTRAINT attribute_family_id_fkey FOREIGN KEY (family_id) REFERENCES family(family_id) DEFERRABLE;


--
-- Name: product_attribute product_attribute_attribute_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY product_attribute
    ADD CONSTRAINT product_attribute_attribute_id_fkey FOREIGN KEY (attribute_id) REFERENCES attribute(attribute_id) DEFERRABLE;


--
-- Name: product_attribute product_attribute_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: florian
--

ALTER TABLE ONLY product_attribute
    ADD CONSTRAINT product_attribute_product_id_fkey FOREIGN KEY (product_id) REFERENCES product(product_id) DEFERRABLE;


--
-- PostgreSQL database dump complete
--

